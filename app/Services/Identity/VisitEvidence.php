<?php

namespace App\Services\Identity;

use App\Models\CitizenAttachment;
use App\Models\IdentityCardForm;
use App\Models\IdentityVisitEvidence;
use App\Models\User;
use App\Services\Attachments\ValidateAttachmentFile;
use App\Services\FingerprintService;
use App\Services\Operations\LockedRequest;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VisitEvidence
{
    private function editable(IdentityCardForm $form): void
    {
        abort_unless(FingerprintService::enabled(), 403);
        abort_unless($form->journey_version === 1 && $form->application->status === 'accepted' && $form->arrived_at
            && ! $form->application->issuedDocument()->exists() && $form->status !== 'demo_completed', 409, 'الأدلة متاحة بعد الحضور وقبل الاعتماد والإصدار.');
        if ($form->operations()->where('form_revision', $form->revision)->where('result', 'success')->exists()) {
            app(IdentityJourney::class)->fail('سجلت بصمة ناجحة لهذه النسخة؛ يلزم طلب تصحيح إداري قبل تغيير أدلتها.');
        }
    }

    public function store(User $actor, IdentityCardForm $form, string $kind, UploadedFile $file, int $version): IdentityVisitEvidence
    {
        $path = null;
        try {
            return app(LockedRequest::class)->run($actor, $form->application, 'recordOperations', function ($actor, $owner, $service, $request) use ($form, $kind, $file, $version, &$path) {
                $form = IdentityCardForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
                abort_unless($form->version === $version && IdentityJourney::applies($request), 409);
                $this->editable($form);
                abort_unless(in_array($kind, ['blood_result', 'card_photo'], true), 422);
                $metadata = app(ValidateAttachmentFile::class)->inspect($file, ['max_kb' => 5120, 'allowed_mime_types' => $kind === 'card_photo' ? ['image/jpeg', 'image/png'] : ['image/jpeg', 'image/png', 'application/pdf']]);
                if ($kind === 'card_photo') {
                    Validator::make(['file' => $file], ['file' => ['image', 'dimensions:min_width='.config('identity_card.photo.min_width').',min_height='.config('identity_card.photo.min_height'), 'max:'.config('identity_card.photo.max_kb')]])->validate();
                }
                $path = 'citizen-files/'.Str::uuid().'.'.$metadata['extension'];
                unset($metadata['extension']);
                $stream = fopen($file->getRealPath(), 'rb');
                try {
                    if (! Storage::disk('attachments')->put($path, $stream, ['visibility' => 'private'])) {
                        throw new \RuntimeException('Private evidence storage unavailable');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $attachment = (new CitizenAttachment)->forceFill($metadata + ['user_id' => $owner->id, 'government_service_id' => $service->id,
                    'citizen_service_request_id' => $request->id, 'document_code' => 'identity_visit_'.$kind, 'document_name' => $kind === 'card_photo' ? 'صورة البطاقة' : 'نتيجة فحص الدم الخارجي',
                    'kind' => $kind === 'card_photo' ? 'photo' : 'document', 'slot_key' => hash('sha256', (string) Str::uuid()),
                    'rules_version' => $service->rules_version, 'storage_path' => $path, 'version_token' => (string) Str::uuid(), 'review_status' => 'pending']);
                if (! $attachment->intact()) {
                    throw new \RuntimeException('Private evidence integrity failure');
                }
                $attachment->save();
                $evidence = (new IdentityVisitEvidence)->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $form->revision,
                    'kind' => $kind, 'citizen_attachment_id' => $attachment->id, 'status' => 'pending', 'recorded_by' => $actor->id]);
                $evidence->save();
                $form->forceFill(['version' => $form->version + 1])->save();
                app(AuditTrail::class)->record('identity_visit_evidence_uploaded', IdentityVisitEvidence::class, $evidence->id, $actor, changes: ['action' => $kind]);

                return $evidence;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('attachments')->delete($path);
            }
            throw $e;
        }
    }

    public function review(User $actor, IdentityVisitEvidence $evidence, string $status, ?string $reason, int $version): void
    {
        app(LockedRequest::class)->run($actor, $evidence->form->application, 'recordOperations', function ($actor, $owner, $service, $request) use ($evidence, $status, $reason, $version) {
            $form = IdentityCardForm::whereKey($evidence->identity_card_form_id)->lockForUpdate()->firstOrFail();
            $this->editable($form);
            abort_unless($form->version === $version && IdentityJourney::applies($request), 409);
            $row = IdentityVisitEvidence::whereKey($evidence->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->form_revision === $form->revision && app(IdentityJourney::class)->evidence($form, $row->kind)?->id === $row->id && $row->status === 'pending', 409);
            Validator::make(['status' => $status, 'reason' => $reason], ['status' => ['required', 'in:approved,rejected,requires_correction'], 'reason' => ['nullable', 'required_unless:status,approved', 'string', 'min:3', 'max:1000']])->validate();
            abort_unless($row->attachment->intact(), 409);
            $row->forceFill(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'reason' => $reason])->save();
            $form->forceFill(['version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_visit_evidence_reviewed', IdentityVisitEvidence::class, $row->id, $actor, changes: ['status' => $status]);
        });
    }
}
