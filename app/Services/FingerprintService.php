<?php

namespace App\Services;

use App\Contracts\FingerprintConnector;
use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Http\Requests\SaveIdentityCardForm;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\IdentityAdministrativeDecision;
use App\Models\IdentityCardForm;
use App\Models\IdentityFingerprintOperation;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Oversight\AuditTrail;
use App\Services\Requirements\RequirementResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FingerprintService
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('identity_card.fingerprint.simulation_enabled') === true;
    }

    public static function canUse(User $actor, string $permission = 'can_review'): bool
    {
        return self::enabled() && $actor->is_active && ($actor->role === 'admin'
            || ($actor->role === 'employee' && $actor->canReviewService(GovernmentService::where('code', 'identity-first-issue')->value('id'), $permission)));
    }

    public function scopedForms(User $actor, string $permission = 'can_review')
    {
        abort_unless(self::canUse($actor, $permission), 403);

        return IdentityCardForm::whereNotNull('submitted_at')->where('user_id', '!=', $actor->id)
            ->when($actor->role !== 'admin', fn ($query) => $query->whereIn('government_service_id', $actor->reviewPermissions()->where($permission, true)->pluck('government_service_id')));
    }

    private function lock(User $actor, IdentityCardForm $form, string $ability, int $version): IdentityCardForm
    {
        User::whereKey($form->user_id)->lockForUpdate()->firstOrFail();
        GovernmentService::whereKey($form->government_service_id)->lockForUpdate()->firstOrFail();
        $request = CitizenServiceRequest::whereKey($form->citizen_service_request_id)->lockForUpdate()->firstOrFail();
        $form = IdentityCardForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor->fresh())->authorize($ability, $form);
        abort_unless($form->submitted_at && $request->user_id === $form->user_id && $request->government_service_id === $form->government_service_id, 409);
        abort_unless(in_array($request->status, ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved'], true), 409, 'الطلب مغلق أو انتقل لمرحلة أخرى.');
        abort_unless($form->version === $version, 409, 'تغيرت الاستمارة؛ حدّث الصفحة وراجع النسخة الحالية.');

        return $form;
    }

    public function prerequisiteReasons(IdentityCardForm $form, bool $arrival = false): array
    {
        $form->load(['photo', 'witnesses', 'attestation', 'service', 'citizen', 'application']);
        $reasons = [];
        if (! $form->submitted_at || $form->current_step !== 7 || $form->correctionIsOpen()) {
            $reasons[] = 'لم تُرسل النسخة الحالية أو توجد بيانات مطلوب تصحيحها.';
        }
        if (! $form->citizen->is_active || ! $form->service->is_active || ! $form->service->rules_approved || ! filled($form->service->source_reference)) {
            $reasons[] = 'الحساب أو الخدمة غير متاحين أو لم تُعتمد إعدادات الخدمة.';
        }
        if (! in_array($form->application?->status, ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved'], true)) {
            $reasons[] = 'حالة الطلب الأساسية لا تسمح باستكمال هذه المرحلة.';
        }
        $witnesses = $form->witnesses->map(function ($witness) {
            $data = $witness->only(['name', 'document_type', 'document_number', 'issued_place', 'issued_at', 'employer', 'work_address', 'phone', 'address']);
            $data['issued_at'] = $witness->issued_at?->format('Y-m-d');

            return $data;
        })->all();
        if (Validator::make(['data' => $form->data, 'witnesses' => $witnesses], SaveIdentityCardForm::dataRules() + SaveIdentityCardForm::witnessRules(true))->fails()) {
            $reasons[] = 'البيانات الشخصية أو بيانات الشاهدين غير مكتملة أو غير صحيحة.';
        }
        $photo = $form->photo;
        if (! $photo || $photo->review_status !== 'approved' || ! $photo->reviewed_by || ! $photo->reviewed_at?->lte(now()) || $photo->rules_version !== $form->service->rules_version || ! $photo->intact()) {
            $reasons[] = 'الصورة الشخصية تحتاج إلى مراجعة موظف معتمدة وسلامة الملف.';
        } else {
            $size = @getimagesize(Storage::disk('attachments')->path($photo->storage_path));
            if (! $size || $size[0] < config('identity_card.photo.min_width') || $size[1] < config('identity_card.photo.min_height') || $photo->size > config('identity_card.photo.max_kb') * 1024 || ! in_array($photo->mime_type, ['image/jpeg', 'image/png'], true)) {
                $reasons[] = 'الصورة الحالية لا تستوفي قواعد الحجم أو الأبعاد أو الصيغة.';
            }
        }
        foreach (app(IdentityAttestations::class)->requirements($form->application, ServiceRequestStatus::Approved) as $check) {
            if ($check->state !== RequirementState::Met) {
                $reasons[] = $check->message;
            }
        }
        if ($arrival && (! $form->arrived_at?->lte(now()) || ! $form->received_by || ! $form->identity_documents_checked_at?->lte(now()) || ! filled($form->arrival_reference))) {
            $reasons[] = 'يلزم تسجيل حضور المواطن وفحص هويته ووثائقه مع مرجع الإجراء أولًا.';
        }

        return array_values(array_unique($reasons));
    }

    public function inputFingerprint(IdentityCardForm $form): string
    {
        $form->load(['photo', 'attestation', 'witnesses', 'service']);

        return hash('sha256', json_encode([
            'form' => $form->id, 'revision' => $form->revision, 'data' => $form->data,
            'photo' => $form->photo?->only(['id', 'version_token', 'sha256', 'rules_version', 'review_status', 'reviewed_by', 'reviewed_at']),
            'attestation' => $form->attestation?->only(['id', 'form_revision', 'status', 'decided_at', 'security_status', 'security_reference', 'security_recorded_by', 'security_recorded_at']),
            'witnesses' => $form->witnesses->map(fn ($row) => $row->only(['position', 'name', 'document_number', 'document_type', 'issued_place', 'issued_at', 'phone', 'address', 'attendance_status', 'verification_status', 'reviewed_by', 'reviewed_at']))->all(),
            'arrival' => $form->only(['arrived_at', 'received_by', 'identity_documents_checked_at', 'arrival_reference']),
            'service' => $form->service->submissionFingerprint(),
        ], JSON_THROW_ON_ERROR));
    }

    public function successfulOperation(IdentityCardForm $form): ?IdentityFingerprintOperation
    {
        $operation = $form->operations()->where('form_revision', $form->revision)->where('result', 'success')->first();

        return $operation && hash_equals($operation->input_fingerprint, $this->inputFingerprint($form)) ? $operation : null;
    }

    private function assertReady(IdentityCardForm $form, bool $arrival = true): void
    {
        if ($reasons = $this->prerequisiteReasons($form, $arrival)) {
            throw ValidationException::withMessages(['requirements' => $reasons]);
        }
    }

    public function arrive(User $actor, IdentityCardForm $form, array $data): IdentityCardForm
    {
        return DB::transaction(function () use ($actor, $form, $data) {
            $form = $this->lock($actor, $form, 'recordFingerprint', (int) $data['version']);
            abort_unless(in_array($form->status, ['pending_fingerprint_attendance', 'fingerprint_failed'], true), 409, 'الطلب ليس بانتظار الاستقبال.');
            $this->assertReady($form, false);
            if (! $form->arrived_at) {
                $form->forceFill(['arrived_at' => now(), 'received_by' => $actor->id, 'arrival_reference' => $data['evidence_reference'], 'identity_documents_checked_at' => now(), 'version' => $form->version + 1])->save();
                app(AuditTrail::class)->record('identity_citizen_arrived', IdentityCardForm::class, $form->id, $actor);
            }

            return $form;
        });
    }

    public function securityPaper(User $actor, IdentityCardForm $form, array $data): IdentityCardForm
    {
        return DB::transaction(function () use ($actor, $form, $data) {
            $form = $this->lock($actor, $form, 'recordFingerprint', (int) $data['version']);
            $row = $form->attestation()->lockForUpdate()->first();
            abort_unless($row?->status === 'approved' && $row->security_required && $row->security_status === 'pending' && $form->status === 'pending_security_review', 409, 'لا يوجد إجراء ورقي معلق لهذه النسخة.');
            $row->forceFill(['security_status' => 'recorded_manually', 'security_reference' => $data['evidence_reference'], 'security_recorded_by' => $actor->id, 'security_recorded_at' => now()])->save();
            $form->forceFill(['status' => 'pending_fingerprint_attendance', 'version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_security_paper_recorded', IdentityCardForm::class, $form->id, $actor);

            return $form;
        });
    }

    public function capture(User $actor, IdentityCardForm $form, array $data, bool $manual = false): IdentityFingerprintOperation
    {
        return DB::transaction(function () use ($actor, $form, $data, $manual) {
            Gate::forUser($actor->fresh())->authorize('recordFingerprint', $form);
            $existing = IdentityFingerprintOperation::where('operation_token', $data['operation_token'])->first();
            if ($existing) {
                abort_unless($existing->identity_card_form_id === $form->id && $existing->recorded_by === $actor->id && $existing->form_revision === $form->revision && $existing->source === ($manual ? 'administrative_real_device' : 'simulation'), 409);

                return $existing;
            }
            $form = $this->lock($actor, $form, 'recordFingerprint', (int) $data['version']);
            abort_unless(in_array($form->status, ['pending_fingerprint_attendance', 'fingerprint_failed'], true), 409, 'لا يمكن تكرار نجاح المرحلة؛ يلزم إجراء تصحيح مخول لفتح نسخة جديدة.');
            $this->assertReady($form);
            abort_if($form->operations()->where('form_revision', $form->revision)->where('result', 'success')->exists(), 409);
            if ($manual) {
                abort_unless(config('identity_card.fingerprint.manual_result_enabled') === true, 403);
                $result = $data['result'];
                $error = $result === 'failure' ? 'فشل جهاز سجل الموظف نتيجته إداريًا؛ لا يوجد اتصال آلي موثق بالجهاز.' : null;
            } else {
                $captured = app(FingerprintConnector::class)->capture((bool) ($data['simulate_failure'] ?? false));
                $result = $captured->result;
                $error = $captured->error;
            }
            abort_unless(in_array($result, ['success', 'failure'], true), 503);
            $operation = new IdentityFingerprintOperation;
            $operation->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $form->revision,
                'reference' => ($manual ? 'MAN-FP-' : 'SIM-FP-').Str::ulid(), 'operation_token' => $data['operation_token'],
                'source' => $manual ? 'administrative_real_device' : 'simulation', 'result' => $result,
                'input_fingerprint' => $this->inputFingerprint($form), 'success_key' => $result === 'success' ? hash('sha256', $form->id.':'.$form->revision) : null,
                'recorded_by' => $actor->id, 'executed_at' => $manual ? $data['executed_at'] : now(), 'error_detail' => $error,
                'evidence_reference' => $manual ? $data['evidence_reference'] : null,
            ])->save();
            $form->forceFill(['status' => $result === 'success' ? 'pending_administrative_review' : 'fingerprint_failed',
                'fingerprint_status' => $manual ? ($result === 'success' ? 'manual_success' : 'manual_failure') : ($result === 'success' ? 'simulation_completed' : 'simulation_failed'), 'version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_fingerprint_recorded', IdentityFingerprintOperation::class, $operation->id, $actor, changes: ['status' => $result, 'action' => $operation->source]);
            $this->notify($form, 'fingerprint:'.$operation->id, $result === 'success' ? 'اكتملت خطوة البصمة التجريبية وأحيل الطلب للمراجعة الإدارية؛ راجع المصدر المعروض.' : 'لم تنجح الخطوة التجريبية؛ يمكن للموظف المخول إعادة المحاولة.');

            return $operation;
        });
    }

    public function administrationReasons(IdentityCardForm $form): array
    {
        $reasons = $this->prerequisiteReasons($form, true);
        if (! $this->successfulOperation($form)) {
            $reasons[] = 'يلزم نجاح خطوة البصمة للنسخة الحالية دون تغير بياناتها أو صورتها أو مصادقتها.';
        }

        return $reasons;
    }

    public function administer(User $actor, IdentityCardForm $form, array $data): IdentityAdministrativeDecision
    {
        return DB::transaction(function () use ($actor, $form, $data) {
            $form = $this->lock($actor, $form, 'administer', (int) $data['version']);
            abort_unless($form->status === 'pending_administrative_review' || ($form->status === 'returned_to_staff' && in_array($data['action'], ['correction_required', 'rejected'], true)), 409, 'الطلب ليس في مرحلة المراجعة الإدارية، أو يحتاج نسخة تصحيح جديدة.');
            if ($data['action'] === 'approved' && ($reasons = $this->administrationReasons($form))) {
                throw ValidationException::withMessages(['requirements' => $reasons]);
            }
            $operation = $form->operations()->where('form_revision', $form->revision)->where('result', 'success')->first();
            $decision = new IdentityAdministrativeDecision;
            $decision->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $data['action'] === 'correction_required' ? $form->revision + 1 : $form->revision,
                'identity_fingerprint_operation_id' => $operation?->id, 'action' => $data['action'],
                'expected_version' => $form->version, 'decision_token' => $data['decision_token'],
                'internal_note' => $data['internal_note'] ?? null, 'citizen_reason' => $data['citizen_reason'] ?? null,
                'decided_by' => $actor->id, 'decided_at' => now(),
            ])->save();
            $changes = ['status' => match ($data['action']) {
                'approved' => 'demo_completed', 'rejected' => 'rejected', 'returned_to_staff' => 'returned_to_staff', 'correction_required' => 'awaiting_data',
            }, 'version' => $form->version + 1];
            if ($data['action'] === 'correction_required') {
                // The next revision never inherits an earlier attestation or fingerprint result.
                $changes += ['revision' => $form->revision + 1, 'current_step' => 1, 'correction_opened_at' => now(), 'correction_opened_by' => $actor->id,
                    'correction_reason' => $data['citizen_reason'], 'arrived_at' => null, 'received_by' => null,
                    'arrival_reference' => null, 'identity_documents_checked_at' => null, 'fingerprint_status' => null];
            }
            $form->forceFill($changes)->save();
            app(AuditTrail::class)->record('identity_trial_administration', IdentityAdministrativeDecision::class, $decision->id, $actor, changes: ['action' => $data['action'], 'status' => $form->status]);
            $this->notify($form, 'administration:'.$decision->id, match ($data['action']) {
                'approved' => 'اكتملت الإجراءات التجريبية للطلب. يمكنك معاينة نموذج الطلب وطباعته؛ لم تُصدر بطاقة حكومية فعلية.',
                'correction_required' => 'يلزم تصحيح بيانات الاستمارة. افتحها للاطلاع على السبب وإعادة التقديم.',
                'rejected' => 'رُفضت المرحلة التجريبية للطلب. راجع السبب المسموح بعرضه في الاستمارة.',
                default => 'أعيد الطلب للموظف لاستكمال المراجعة.',
            });

            return $decision;
        });
    }

    public function requeue(User $actor, IdentityCardForm $form, array $data): void
    {
        DB::transaction(function () use ($actor, $form, $data) {
            $form = $this->lock($actor, $form, 'recordFingerprint', (int) $data['version']);
            abort_unless($form->status === 'returned_to_staff', 409);
            if ($reasons = $this->administrationReasons($form)) {
                throw ValidationException::withMessages(['requirements' => $reasons]);
            }
            (new IdentityAdministrativeDecision)->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $form->revision, 'action' => 'requeued', 'expected_version' => $form->version, 'decision_token' => (string) Str::uuid(), 'internal_note' => $data['note'], 'decided_by' => $actor->id, 'decided_at' => now()])->save();
            $form->forceFill(['status' => 'pending_administrative_review', 'version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_returned_to_administration', IdentityCardForm::class, $form->id, $actor);
        });
    }

    public function requirements(CitizenServiceRequest $application, ?ServiceRequestStatus $target): array
    {
        $form = $application->identityCardForm;
        if (! $form) {
            return [];
        }
        $stage = $target?->value ?? $application->status;
        $success = self::enabled() && $this->successfulOperation($form);

        return [new RequirementResult('identity_fingerprint_stage', 'خطوة البصمة ومصدرها', $success ? RequirementState::Met : RequirementState::NeedsReview, $success ? 'نجحت خطوة ذات مصدر مصرح للنسخة الحالية؛ المحاكاة لا تثبت التقاط بصمة حيوية.' : 'يلزم استكمال خطوة البصمة المحلية للنسخة الحالية.', mandatory: in_array($stage, ['accepted', 'approved', 'processing', 'completed'], true)),
            new RequirementResult('identity_trial_administration', 'المراجعة الإدارية التجريبية', $success && $form->status === 'demo_completed' ? RequirementState::Met : RequirementState::NeedsReview, 'يلزم اكتمال المراجعة الإدارية التجريبية؛ لا تعني إصدار بطاقة حكومية.', mandatory: in_array($stage, ['processing', 'completed'], true))];
    }

    private function notify(IdentityCardForm $form, string $event, string $body): void
    {
        $form->citizen->notifyNow(new CitizenActivity('identity-workflow:'.$event, ['kind' => 'request_activity', 'title' => 'تحديث استمارة البطاقة', 'body' => $body, 'request_reference' => $form->application->reference, 'occurred_at' => now()->toIso8601String()], $form->citizen_service_request_id));
    }
}
