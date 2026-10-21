<?php

namespace App\Services\Attachments;

use App\Models\CitizenAttachment;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ManageAttachments
{
    public function __construct(private AttachmentRules $rules, private ValidateAttachmentFile $validator) {}

    public function assertUploadContext(User $citizen, GovernmentService $service, ?CitizenServiceRequest $application): void
    {
        abort_unless($citizen->is_active && $citizen->role === 'citizen', 403);
        abort_unless($service->is_active && $service->rules_approved && filled($service->source_reference), 403, 'الخدمة غير متاحة للرفع حاليًا.');
        if ($application) {
            abort_unless($application->user_id === $citizen->id && $application->government_service_id === $service->id, 404);
            abort_unless($application->status === 'requires_correction', 403, 'تعديل المرفقات متاح فقط عندما يكون الطلب بحاجة إلى استكمال.');
        }
    }

    public function store(User $actor, GovernmentService $service, ?CitizenServiceRequest $application, string $code, UploadedFile $file): CitizenAttachment
    {
        $newPath = null;
        try {
            return DB::transaction(function () use ($actor, $service, $application, $code, $file, &$newPath) {
                $citizen = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $service = GovernmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
                $application = $application ? CitizenServiceRequest::whereKey($application->id)->lockForUpdate()->firstOrFail() : null;
                $this->assertUploadContext($citizen, $service, $application);
                $rule = $this->rules->get($service, $code);
                $metadata = $this->validator->inspect($file, $rule);
                $slot = CitizenAttachment::slot($citizen->id, $service->id, $application?->id, $code);
                $attachment = CitizenAttachment::where('slot_key', $slot)->lockForUpdate()->first();
                if ($attachment) {
                    Gate::forUser($citizen)->authorize('update', $attachment);
                }
                $oldPath = $attachment?->storage_path;
                $newPath = 'citizen-files/'.Str::uuid().'.'.$metadata['extension'];
                unset($metadata['extension']);
                $stream = fopen($file->getPathname(), 'rb');
                try {
                    if (! Storage::disk('attachments')->put($newPath, $stream, ['visibility' => 'private'])) {
                        throw new \RuntimeException('Private storage write failed');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $attachment ??= new CitizenAttachment;
                $attachment->forceFill(array_merge($metadata, [
                    'user_id' => $citizen->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $application?->id,
                    'slot_key' => $slot, 'document_code' => $code, 'document_name' => $rule['name'], 'kind' => $rule['kind'],
                    'rules_version' => $service->rules_version, 'storage_path' => $newPath,
                    'version_token' => (string) Str::uuid(),
                    'review_status' => $rule['requires_review'] ? 'pending' : 'uploaded',
                    'reviewed_by' => null, 'reviewed_at' => null, 'public_reason' => null, 'expires_at' => null,
                ]));
                if (! $attachment->intact()) {
                    throw new \RuntimeException('Stored attachment integrity failed');
                }
                $attachment->save();
                $this->invalidate($application);
                DB::afterCommit(function () use ($oldPath, $attachment, $citizen) {
                    if ($oldPath) {
                        $this->removeBytes($oldPath, $attachment->id);
                    }
                    $this->audit($oldPath ? 'replaced' : 'uploaded', $attachment, $citizen);
                });

                return $attachment;
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                $this->removeBytes($newPath);
            }
            if ($exception instanceof AuthorizationException || $exception instanceof ValidationException || $exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            $this->warning('attachment_storage_failed', ['error_type' => get_class($exception)]);
            throw ValidationException::withMessages(['file' => 'تعذر حفظ الملف. بقي المرفق السابق محفوظًا؛ حاول مرة أخرى.']);
        }
    }

    public function delete(User $actor, CitizenAttachment $attachment): void
    {
        DB::transaction(function () use ($actor, $attachment) {
            $citizen = User::whereKey($attachment->user_id)->lockForUpdate()->firstOrFail();
            $service = GovernmentService::whereKey($attachment->government_service_id)->lockForUpdate()->firstOrFail();
            $attachment->refresh();
            $application = $attachment->citizen_service_request_id ? CitizenServiceRequest::whereKey($attachment->citizen_service_request_id)->lockForUpdate()->firstOrFail() : null;
            $file = CitizenAttachment::whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->id === $citizen->id ? $citizen : $actor->fresh())->authorize('update', $file);
            $this->assertUploadContext($citizen, $service, $application);
            if ($this->rules->get($service, $file->document_code)['mandatory']) {
                throw ValidationException::withMessages(['attachment' => 'هذا المرفق إلزامي؛ استبدله بملف صالح بدل حذفه.']);
            }
            $path = $file->storage_path;
            $file->delete();
            $this->invalidate($application);
            DB::afterCommit(function () use ($path, $file, $actor) {
                $this->removeBytes($path, $file->id);
                $this->audit('deleted', $file, $actor);
            });
        });
    }

    public function review(User $actor, CitizenAttachment $attachment, array $data): void
    {
        DB::transaction(function () use ($actor, $attachment, $data) {
            User::whereKey($attachment->user_id)->lockForUpdate()->firstOrFail();
            $service = GovernmentService::whereKey($attachment->government_service_id)->lockForUpdate()->firstOrFail();
            $attachment->refresh();
            $application = $attachment->citizen_service_request_id ? CitizenServiceRequest::whereKey($attachment->citizen_service_request_id)->lockForUpdate()->firstOrFail() : null;
            $file = CitizenAttachment::whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->fresh())->authorize('review', $file);
            if (! hash_equals($file->version_token, $data['expected_version'])) {
                throw ValidationException::withMessages(['attachment' => 'تغير المرفق منذ فتح صفحة المراجعة. عاين النسخة الحالية قبل تسجيل القرار.']);
            }
            $rule = $this->rules->get($service, $file->document_code);
            if ($file->rules_version !== $service->rules_version || ! $file->intact()) {
                throw ValidationException::withMessages(['attachment' => 'تغيرت قواعد الخدمة أو تعذر التحقق من سلامة الملف. يلزم رفع نسخة جديدة.']);
            }
            if ($data['review_status'] === 'approved' && ($rule['requires_expiry'] ?? false) && empty($data['expires_at'])) {
                throw ValidationException::withMessages(['expires_at' => 'تاريخ انتهاء موثق مطلوب لاعتماد هذا المستند.']);
            }
            $file->forceFill(['review_status' => $data['review_status'], 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'public_reason' => $data['public_reason'] ?? null, 'expires_at' => $data['expires_at'] ?? null, 'version_token' => (string) Str::uuid()])->save();
            $this->invalidate($application);
            DB::afterCommit(fn () => $this->audit('reviewed', $file, $actor));
        });
    }

    private function invalidate(?CitizenServiceRequest $application): void
    {
        if ($application) {
            $application->forceFill(['requirements_result' => null, 'requirements_checked_at' => null])->save();
        }
    }

    private function removeBytes(string $path, ?int $id = null): void
    {
        if (! preg_match('~^citizen-files/[a-f0-9-]{36}\.(png|jpg|pdf)$~D', $path)) {
            return;
        }
        try {
            if (! Storage::disk('attachments')->delete($path)) {
                $this->warning('attachment_cleanup_required', ['attachment_id' => $id]);
            }
        } catch (\Throwable) {
            $this->warning('attachment_cleanup_required', ['attachment_id' => $id]);
        }
    }

    private function audit(string $action, CitizenAttachment $file, User $actor): void
    {
        try {
            Log::info('attachment_action', ['action' => $action, 'attachment_id' => $file->id, 'actor_id' => $actor->id, 'request_id' => $file->citizen_service_request_id, 'review_status' => $file->review_status]);
        } catch (\Throwable) {
            // Logging failure after commit must never undo or delete a committed personal file.
        }
    }

    private function warning(string $event, array $context): void
    {
        try {
            Log::warning($event, $context);
        } catch (\Throwable) { /* Preserve committed storage even if logging is unavailable. */
        }
    }
}
