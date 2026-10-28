<?php

namespace App\Services;

use App\Http\Requests\SaveIdentityCardForm;
use App\Models\CitizenAttachment;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\IdentityAdministrativeDecision;
use App\Models\IdentityCardForm;
use App\Models\IdentityCardWitness;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Attachments\ValidateAttachmentFile;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityCardForms
{
    public function create(User $actor): IdentityCardForm
    {
        return DB::transaction(function () use ($actor) {
            $citizen = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($citizen->is_active && $citizen->role === 'citizen', 403);
            $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
            $form = IdentityCardForm::where('user_id', $citizen->id)->whereNull('submitted_at')->first();
            if (! $form) {
                $form = new IdentityCardForm;
                $form->forceFill(['user_id' => $citizen->id, 'government_service_id' => $service->id, 'reference' => 'IDFORM-'.Str::ulid(), 'status' => 'draft', 'current_step' => 1, 'version' => 1, 'data' => ['applicant_name' => $citizen->name, 'phone' => $citizen->phone]])->save();
            }

            return $form;
        });
    }

    private function locked(User $actor, IdentityCardForm $form): IdentityCardForm
    {
        $citizen = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($citizen->is_active && $citizen->role === 'citizen' && $form->user_id === $citizen->id, 403);
        GovernmentService::whereKey($form->government_service_id)->lockForUpdate()->firstOrFail();

        return IdentityCardForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
    }

    public function save(User $actor, IdentityCardForm $form, array $input, ?UploadedFile $photo): IdentityCardForm
    {
        $newPath = null;
        try {
            return DB::transaction(function () use ($actor, $form, $input, $photo, &$newPath) {
                $form = $this->locked($actor, $form);
                $correction = $this->authorizedCorrection($form);
                abort_if($form->submitted_at && ! $correction, 409, 'أُرسل الطلب؛ لا يمكن تعديل المسودة.');
                abort_unless($form->version === (int) $input['version'], 409, 'تغيرت المسودة في نافذة أخرى. حدّث الصفحة.');
                $step = (int) $input['step'];
                abort_if($step > $form->current_step, 409, 'أكمل المرحلة السابقة أولًا.');
                if ($step <= 3) {
                    $form->data = array_replace($form->data ?? [], $input['data'] ?? []);
                }
                if ($step === 4 && $photo) {
                    $this->photo($form, $photo, $newPath);
                }
                if ($step === 4 && $input['action'] === 'next' && ! $form->citizen_attachment_id) {
                    throw ValidationException::withMessages(['photo' => 'ارفع الصورة الشخصية قبل المتابعة.']);
                }
                if ($step === 5) {
                    foreach ($input['witnesses'] ?? [] as $index => $data) {
                        $witness = $form->witnesses()->where('position', $index + 1)->first() ?? new IdentityCardWitness;
                        if ($correction) {
                            $data = array_merge($data, ['verification_status' => 'not_verified', 'attendance_status' => 'not_recorded', 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null]);
                        }
                        $witness->forceFill($data + ['identity_card_form_id' => $form->id, 'position' => $index + 1])->save();
                    }
                }
                $form->current_step = max($form->current_step, $input['action'] === 'next' ? min(6, $step + 1) : $step);
                $form->status = 'awaiting_data';
                $form->version++;
                $form->save();

                return $form;
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                $this->removeBytes($newPath);
            }
            throw $error;
        }
    }

    private function photo(IdentityCardForm $form, UploadedFile $file, ?string &$newPath): void
    {
        try {
            $metadata = app(ValidateAttachmentFile::class)->inspect($file, ['max_kb' => config('identity_card.photo.max_kb'), 'allowed_mime_types' => ['image/jpeg', 'image/png']]);
        } catch (ValidationException $error) {
            throw ValidationException::withMessages(['photo' => $error->errors()['file'] ?? ['تعذر التحقق من الصورة.']]);
        }
        $extension = $metadata['extension'];
        unset($metadata['extension']);
        $rule = collect($form->service->verification_rules['documents'] ?? [])->firstWhere('kind', 'photo');
        $code = $rule['code'] ?? 'identity_photo';
        $correction = $this->authorizedCorrection($form);
        $requestId = $correction ? $form->citizen_service_request_id : null;
        $slot = CitizenAttachment::slot($form->user_id, $form->government_service_id, $requestId, $code);
        $attachment = $form->photo ?? CitizenAttachment::where('slot_key', $slot)->first() ?? new CitizenAttachment;
        abort_if($attachment->citizen_service_request_id && (! $correction || $attachment->citizen_service_request_id !== $requestId), 409);
        abort_if($attachment->exists && ($attachment->user_id !== $form->user_id || $attachment->government_service_id !== $form->government_service_id), 409);
        $oldPath = $attachment->storage_path;
        $newPath = 'citizen-files/'.Str::uuid().'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            abort_unless(Storage::disk('attachments')->put($newPath, $stream, ['visibility' => 'private']), 503);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $attachment->forceFill($metadata + ['user_id' => $form->user_id, 'government_service_id' => $form->government_service_id,
            'citizen_service_request_id' => $requestId, 'slot_key' => $slot, 'document_code' => $code,
            'document_name' => $rule['name'] ?? 'الصورة الشخصية لطلب البطاقة', 'kind' => 'photo',
            'rules_version' => $form->service->rules_version, 'storage_path' => $newPath, 'version_token' => (string) Str::uuid(),
            'review_status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'public_reason' => null, 'expires_at' => null,
        ])->save();
        abort_unless($attachment->intact(), 503);
        $form->citizen_attachment_id = $attachment->id;
        if ($oldPath && $oldPath !== $newPath) {
            DB::afterCommit(fn () => $this->removeBytes($oldPath));
        }
    }

    private function authorizedCorrection(IdentityCardForm $form): bool
    {
        if (! $form->correctionIsOpen()) {
            return false;
        }
        abort_unless($form->application && in_array($form->application->status, ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved'], true), 409, 'الطلب مغلق ولا يمكن تعديل استمارته.');
        abort_unless($form->citizen_service_request_id && IdentityAdministrativeDecision::where('identity_card_form_id', $form->id)
            ->where('form_revision', $form->revision)->where('action', 'correction_required')->exists(), 409);

        return true;
    }

    private function removeBytes(string $path): void
    {
        try {
            Storage::disk('attachments')->delete($path);
        } catch (\Throwable) {
            try {
                Log::warning('identity_photo_cleanup_deferred');
            } catch (\Throwable) {
            }
        }
    }

    public function reviewFingerprint(User $actor, IdentityCardForm $form): string
    {
        return hash('sha256', json_encode([
            'owner' => $actor->id, 'form' => $form->id, 'version' => $form->version,
            'photo' => $form->photo?->only(['id', 'version_token', 'sha256', 'review_status', 'rules_version']),
            'service' => $form->service->submissionFingerprint(),
            'citizen' => ServiceRequestSubmission::citizenFingerprint($actor),
        ], JSON_THROW_ON_ERROR));
    }

    public function submit(User $actor, IdentityCardForm $form, int $version, ?array $review): IdentityCardForm
    {
        return DB::transaction(function () use ($actor, $form, $version, $review) {
            $form = $this->locked($actor, $form);
            // A repeated confirmation returns the same request; never creates a new reference.
            if ($form->submitted_at && ! $form->correctionIsOpen()) {
                return $form;
            }
            $correction = $this->authorizedCorrection($form);
            abort_unless($form->version === $version, 409, 'تغيرت المسودة. راجع ملخص الطلب مجددًا.');
            if (! $review || ($review['expires_at'] ?? 0) < now()->timestamp || ! hash_equals($review['fingerprint'], $this->reviewFingerprint($actor->fresh(), $form))) {
                throw ValidationException::withMessages(['review_token' => 'تغيرت الصورة أو البيانات أو إعدادات الخدمة، أو انتهت صلاحية الملخص. افتح مرحلة المراجعة مجددًا قبل الإرسال.']);
            }
            abort_unless($form->current_step === 6, 409, 'أكمل مراحل الطلب أولًا.');
            Gate::forUser($actor)->authorize('create', CitizenServiceRequest::class);
            $witnesses = $form->witnesses->map(fn ($witness) => $witness->only(['name', 'document_type', 'document_number', 'issued_place', 'issued_at', 'employer', 'work_address', 'phone', 'address']))->all();
            foreach ($witnesses as &$witness) {
                $witness['issued_at'] = $witness['issued_at']?->format('Y-m-d');
            }
            unset($witness);
            Validator::make(['data' => $form->data, 'witnesses' => $witnesses], SaveIdentityCardForm::dataRules() + SaveIdentityCardForm::witnessRules(true), SaveIdentityCardForm::validationMessages())->validate();
            $photo = $form->photo;
            if (! $photo || ! $photo->intact() || in_array($photo->review_status, ['rejected', 'replacement_required'], true)) {
                throw ValidationException::withMessages(['photo' => 'الصورة مفقودة أو مرفوضة؛ عد لمرحلة الصورة واستبدلها.']);
            }
            $dimensions = @getimagesize(Storage::disk('attachments')->path($photo->storage_path));
            if (! $dimensions || $photo->size > config('identity_card.photo.max_kb') * 1024
                || $dimensions[0] < config('identity_card.photo.min_width') || $dimensions[1] < config('identity_card.photo.min_height')
                || ! in_array($photo->mime_type, ['image/jpeg', 'image/png'], true)) {
                throw ValidationException::withMessages(['photo' => 'الصورة الحالية لا تستوفي الحجم أو الأبعاد أو الصيغة المطلوبة؛ عد لمرحلة الصورة واستبدلها.']);
            }
            if ($correction) {
                $application = CitizenServiceRequest::whereKey($form->citizen_service_request_id)->lockForUpdate()->firstOrFail();
                abort_unless($application->user_id === $actor->id && $application->government_service_id === $form->government_service_id
                    && in_array($application->status, ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved'], true), 409);
                abort_unless($photo->user_id === $form->user_id && $photo->government_service_id === $form->government_service_id
                    && $photo->citizen_service_request_id === $application->id, 409);
                $form->forceFill(['status' => 'pending_attestation', 'correction_opened_at' => null, 'declared_at' => now(),
                    'resubmitted_at' => now(), 'current_step' => 7, 'version' => $form->version + 1])->save();
                app(AuditTrail::class)->record('identity_correction_resubmitted', IdentityCardForm::class, $form->id, $actor,
                    changes: ['changed_fields' => ['data', 'witnesses', 'citizen_attachment_id', 'declared_at', 'resubmitted_at'], 'status' => $form->status]);
                $actor->notifyNow(new CitizenActivity('identity-correction:'.$form->id.':'.$form->revision.':resubmitted', [
                    'kind' => 'request_activity', 'title' => 'تم إرسال التصحيح', 'body' => 'تم إرسال الاستمارة المصححة للمصادقة والمراجعة.',
                    'request_reference' => $application->reference, 'occurred_at' => now()->toIso8601String(),
                ], $application->id));

                return $form;
            }
            $submission = app(ServiceRequestSubmission::class);
            $preview = $submission->preview($actor, $form->service, ['data' => $form->data]);
            $application = $submission->submit($actor, $form->service, 'identity-form-'.$form->id, $preview);
            $form->forceFill(['citizen_service_request_id' => $application->id, 'status' => 'pending_photo_review', 'declared_at' => now(), 'submitted_at' => now(), 'current_step' => 7, 'version' => $form->version + 1])->save();

            return $form;
        });
    }
}
