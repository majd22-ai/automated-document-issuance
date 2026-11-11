<?php

namespace App\Services;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
use App\Models\NeighborhoodOfficial;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Oversight\AuditTrail;
use App\Services\Requirements\RequirementResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityAttestations
{
    public function recipient(array $data, ?IdentityCardForm $form = null): NeighborhoodOfficial
    {
        $official = NeighborhoodOfficial::with('district.governorate')->whereKey($data['official_id'] ?? null)->lockForUpdate()->first();
        if (! $official || ! $official->is_active) {
            throw ValidationException::withMessages(['official_id' => 'اختر عاقلًا مسجلًا ومفعّلًا.']);
        }
        if ($official->district_id !== (int) ($data['district_id'] ?? 0) || $official->district->governorate_id !== (int) ($data['governorate_id'] ?? 0)) {
            throw ValidationException::withMessages(['district_id' => 'المديرية أو العاقل لا يتبع المحافظة المحددة.']);
        }
        $normalize = fn ($value) => preg_replace('/\s+/u', ' ', trim((string) $value));
        if ($normalize($data['neighborhood'] ?? '') !== $normalize($official->neighborhood)
            || ($form && $normalize($form->data['neighborhood'] ?? '') !== $normalize($official->neighborhood))) {
            throw ValidationException::withMessages(['neighborhood' => 'يجب أن يطابق حي السكن في الاستمارة الحي المسؤول عنه العاقل. راجع البيانات قبل طلب المصادقة.']);
        }
        if ($form) {
            foreach (['residence_governorate' => $official->district->governorate->name, 'residence_district' => $official->district->name] as $field => $name) {
                if (filled($form->data[$field] ?? null) && $normalize($form->data[$field]) !== $normalize($name)) {
                    throw ValidationException::withMessages(['district_id' => 'المحافظة والمديرية يجب أن تطابقا عنوان الإقامة المحفوظ في الاستمارة.']);
                }
            }
        }

        return $official;
    }

    public static function enabled(): bool
    {
        return app()->environment('local') && config('identity_card.simulation_enabled') === true;
    }

    public static function canUseSimulation(User $actor): bool
    {
        if (! self::enabled() || ! $actor->is_active) {
            return false;
        }
        $service = GovernmentService::where('code', 'identity-first-issue')->value('id');

        return $actor->role === 'admin' || ($actor->role === 'employee' && $actor->canReviewService($service, 'can_review'));
    }

    public function visibleTo(User $actor)
    {
        abort_unless(self::canUseSimulation($actor), 403);

        return IdentityCardAttestation::whereNull('recipient_scope->employee_id')->whereHas('form', function ($query) use ($actor) {
            $query->where('user_id', '!=', $actor->id);
            if ($actor->role !== 'admin') {
                $query->whereIn('government_service_id', $actor->reviewPermissions()->where('can_review', true)->pluck('government_service_id'));
            }
        });
    }

    private function lock(IdentityCardForm $form): IdentityCardForm
    {
        // Keep the owner and service locks before a linked request, as in submission workflows.
        User::whereKey($form->user_id)->lockForUpdate()->firstOrFail();
        GovernmentService::whereKey($form->government_service_id)->lockForUpdate()->firstOrFail();
        $form = IdentityCardForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
        if ($form->submitted_at) {
            $application = CitizenServiceRequest::whereKey($form->citizen_service_request_id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($application->status, ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved'], true), 409, 'حالة الطلب الحالية لا تسمح بإجراء مصادقة.');
            abort_unless($form->government_service_id === $application->government_service_id && $form->user_id === $application->user_id, 409);
        } else {
            abort_unless(! $form->citizen_service_request_id && $form->current_step === 6 && $form->status === 'awaiting_data', 409, 'أكمل بيانات الشاهدين أولًا لطلب مصادقة العاقل.');
        }

        return $form;
    }

    public function choose(User $actor, IdentityCardForm $form, array $data): IdentityCardAttestation
    {
        return DB::transaction(function () use ($actor, $form, $data) {
            $form = $this->lock($form);
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('chooseAttestation', $form);
            $existing = IdentityCardAttestation::where('identity_card_form_id', $form->id)->lockForUpdate()->first();
            if ($existing && $existing->form_revision === $form->revision && in_array($existing->status, ['pending', 'approved'], true)) {
                abort_unless($existing->authority_type === $data['authority_type'] && $existing->neighborhood_official_id === (int) ($data['official_id'] ?? 0), 409, 'تم اختيار مسار المصادقة لهذا الطلب بالفعل.');

                return $existing;
            }
            if ($existing && $existing->form_revision === $form->revision) {
                abort_unless(in_array($existing->status, ['rejected', 'cancelled'], true), 409, 'لا يمكن إعادة طلب المصادقة في حالتها الحالية.');
            }
            $type = $data['authority_type'];
            abort_unless($type === 'neighborhood', 422, 'المصادقة الجديدة عن طريق عاقل الحارة فقط.');
            $official = $this->recipient($data, $form);
            if (! $official->can_attest || ! $official->user || ! NeighborhoodOfficial::authorized($official->user)) {
                throw ValidationException::withMessages(['official_id' => 'لم يُربط العاقل بحساب موظف مخوّل لاعتماد شهادة السكن بعد.']);
            }
            $security = (bool) config('identity_card.attestation_security.'.$type, false);
            $attestation = $existing ?? new IdentityCardAttestation;
            $attestation->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $form->revision, 'citizen_name' => $form->data['applicant_name'] ?? null, 'reference' => 'SIM-ATT-'.Str::ulid(),
                'authority_type' => $type, 'recipient_name' => $official->full_name,
                'neighborhood_official_id' => $official->id,
                'recipient_scope' => ['employee_id' => $official->user_id, 'governorate_id' => $official->district->governorate_id, 'district_id' => $official->district_id, 'governorate' => $official->district->governorate->name, 'district' => $official->district->name, 'neighborhood' => $official->neighborhood],
                'status' => 'pending', 'created_by' => $actor->id, 'notification_created_at' => now(), 'notification_read_at' => null, 'notification_read_by' => null,
                'decided_by' => null, 'decided_at' => null, 'rejection_reason' => null, 'cancelled_at' => null,
                'verification_attempts' => 0, 'verification_source' => null, 'verified_at' => null,
                'security_required' => $security, 'security_status' => $security ? 'pending' : 'not_required',
            ])->save();
            $formChanges = ['version' => $form->version + 1];
            if ($form->submitted_at && ! $form->correctionIsOpen()) {
                $formChanges['status'] = 'pending_neighborhood_attestation';
            }
            $form->forceFill($formChanges)->save();
            app(AuditTrail::class)->record('identity_attestation_requested', IdentityCardAttestation::class, $attestation->id, $actor, changes: ['status' => 'pending', 'action' => $type]);
            $official->user->notifyNow(new CitizenActivity('neighborhood-request:'.$attestation->id.':'.Str::ulid(), [
                'kind' => 'neighborhood_attestation', 'title' => 'طلب اعتماد شهادة سكن جديد',
                'body' => 'لديك طلب شهادة سكن ضمن نطاق حيك. افتح صفحة شهادات السكن لمراجعته. إشعار محلي؛ لم تُرسل رسالة SMS.',
                'attestation_id' => $attestation->id,
                'url' => route('employee.neighborhood-attestations.show', $attestation, false),
                'occurred_at' => now()->toIso8601String(),
            ]));

            return $attestation;
        });
    }

    public function open(User $actor, IdentityCardAttestation $attestation): void
    {
        DB::transaction(function () use ($actor, $attestation) {
            Gate::forUser($actor->fresh())->authorize('simulate', $attestation->form);
            $row = IdentityCardAttestation::whereKey($attestation->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->notification_created_at, 404);
            if (! $row->notification_read_at) {
                $row->forceFill(['notification_read_at' => now(), 'notification_read_by' => $actor->id])->save();
                app(AuditTrail::class)->record('identity_simulated_notice_read', IdentityCardAttestation::class, $row->id, $actor);
            }
        });
    }

    public function decide(User $actor, IdentityCardAttestation $attestation, array $data): IdentityCardAttestation
    {
        $result = DB::transaction(function () use ($actor, $attestation, $data) {
            $form = $this->lock($attestation->form);
            $actor = $actor->fresh();
            if (data_get($attestation->recipient_scope, 'employee_id')) {
                abort_unless($this->canAttest($actor, $attestation), 403);
            } else {
                Gate::forUser($actor)->authorize('simulate', $form);
            }
            $row = IdentityCardAttestation::whereKey($attestation->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->form_revision === $form->revision, 409, 'المصادقة تخص نسخة سابقة من الاستمارة.');
            abort_unless($row->status === 'pending', 409, 'سبق اتخاذ قرار أو إلغاء المصادقة؛ لا يمكن تكراره.');
            if ($row->neighborhood_official_id) {
                $scope = $row->recipient_scope ?? [];
                $this->recipient($scope + ['official_id' => $row->neighborhood_official_id], $form);
                if ($data['decision'] === 'approved') {
                    if (data_get($row->recipient_scope, 'employee_id')) {
                        $proof = session()->pull('neighborhood_confirmation.'.$row->id);
                        abort_unless(self::enabled() && is_array($proof) && ($proof['actor'] ?? null) === $actor->id
                            && ($proof['revision'] ?? null) === $form->revision && ($proof['expires'] ?? 0) >= time()
                            && hash_equals($proof['token_hash'] ?? '', hash('sha256', (string) ($data['confirmation_token'] ?? ''))), 422, 'أكمل نافذة التأكيد المحلي أولًا.');
                        $row->forceFill(['verification_source' => 'local_employee_confirmation', 'verified_at' => now()]);
                    } else {
                        if ($row->verification_attempts >= 5) {
                            return ['verification_code' => 'تجاوزت حد محاولات التحقق التجريبي. راجع المسؤول.'];
                        }
                        $row->verification_attempts++;
                        $method = $data['verification_method'] ?? null;
                        $code = config('identity_card.neighborhood_test_code');
                        $verified = $method === 'fingerprint' || ($method === 'code' && is_string($code) && preg_match('/^\d{6}$/D', $code) && hash_equals($code, (string) ($data['verification_code'] ?? '')));
                        if (! $verified) {
                            $row->save();
                            app(AuditTrail::class)->record('neighborhood_simulation_verification', IdentityCardAttestation::class, $row->id, $actor, result: 'failed', changes: ['action' => 'local_verification', 'status' => 'failed']);

                            return ['verification_code' => 'فشل التحقق التجريبي. الرمز غير صحيح أو لم يُضبط رمز البيئة المحلية.'];
                        }
                        $row->forceFill(['verification_source' => $method === 'code' ? 'local_code' : 'local_fingerprint', 'verified_at' => now()]);
                        app(AuditTrail::class)->record('neighborhood_simulation_verification', IdentityCardAttestation::class, $row->id, $actor, changes: ['action' => $row->verification_source, 'status' => 'verified']);
                    }
                }
            }
            if ($row->authority_type === 'witnesses') {
                if ($data['decision'] === 'approved' && (count($data['witnesses'] ?? []) !== 2 || collect($data['witnesses'])->contains(fn ($result) => ($result['attendance'] ?? null) !== 'present' || ($result['verification'] ?? null) !== 'approved'))) {
                    throw ValidationException::withMessages(['witnesses' => 'لا يمكن الموافقة قبل تسجيل حضور الشاهدين والتحقق من وثائقهما.']);
                }
                $witnesses = $form->witnesses()->lockForUpdate()->get();
                abort_unless($witnesses->count() === 2 && $witnesses->pluck('position')->all() === [1, 2], 409, 'بيانات الشاهدين غير مكتملة.');
                foreach ($witnesses as $index => $witness) {
                    $result = $data['witnesses'][$index];
                    $witness->forceFill(['attendance_status' => $result['attendance'], 'verification_status' => $result['verification'], 'review_note' => $result['note'] ?? null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
                }
            }
            $approved = $data['decision'] === 'approved';
            $row->forceFill(['status' => $data['decision'], 'decided_by' => $actor->id, 'decided_at' => now(), 'rejection_reason' => $approved ? null : $data['reason']])->save();
            $gateBeforeSubmission = ! $form->submitted_at || $form->correctionIsOpen();
            $formChanges = ['version' => $form->version + 1];
            if ($gateBeforeSubmission) {
                $formChanges['status'] = 'awaiting_data';
                if ($approved) {
                    $formChanges['current_step'] = 7;
                }
            } else {
                $formChanges['status'] = ! $approved ? 'rejected' : ($row->security_required ? 'pending_security_review' : 'pending_fingerprint_attendance');
            }
            $form->forceFill($formChanges)->save();
            app(AuditTrail::class)->record('identity_attestation_decided', IdentityCardAttestation::class, $row->id, $actor, changes: ['status' => $row->status, 'action' => $row->authority_type]);
            $form->citizen->notifyNow(new CitizenActivity('identity-attestation:'.$row->id.':decided', [
                'kind' => 'request_activity',
                'title' => $approved ? 'تمت المصادقة على شهادة السكن' : 'رُفضت المصادقة على شهادة السكن',
                'body' => $approved ? 'تم اعتماد شهادة السكن وختم العاقل التجريبي في ملفك. راجع الطلب للخطوة التالية؛ ليس ختمًا حكوميًا.' : 'تم رفض اعتماد ختم العاقل التجريبي. راجع استمارة الطلب للاطلاع على السبب.',
                'identity_form_id' => $form->id,
                'request_reference' => $form->application?->reference ?? $form->reference, 'occurred_at' => now()->toIso8601String(),
            ], $form->citizen_service_request_id));

            return $row;
        });
        if (is_array($result)) {
            throw ValidationException::withMessages($result);
        }

        return $result;
    }

    public function requirements(CitizenServiceRequest $application, ?ServiceRequestStatus $target): array
    {
        $form = $application->identityCardForm;
        if (! $form) {
            return [];
        }
        $required = in_array(($target?->value ?? $application->status), ['accepted', 'approved', 'processing', 'completed'], true);
        $attestation = $form->attestation;
        $approved = self::enabled() && $attestation?->status === 'approved';
        if ($approved && $attestation->neighborhood_official_id) {
            $approved = $attestation->verified_at && in_array($attestation->verification_source, ['local_code', 'local_fingerprint', 'local_employee_confirmation'], true);
        }
        if ($approved && $attestation->authority_type === 'witnesses') {
            $approved = $form->witnesses()->where('attendance_status', 'present')->where('verification_status', 'approved')->whereNotNull('reviewed_at')->count() === 2;
        }
        $checks = [new RequirementResult('identity_attestation', 'المصادقة على استمارة البطاقة', $approved ? RequirementState::Met : ($attestation?->status === 'rejected' ? RequirementState::Unmet : RequirementState::NeedsReview),
            $approved ? 'سُجلت مصادقة تجريبية داخل المنصة؛ لا تمثل ختمًا حكوميًا.' : 'يلزم استكمال مسار المصادقة؛ لا يمكن تجاوز قرار معلق أو مرفوض.', mandatory: $required)];
        if ($attestation?->security_required) {
            $recorded = $attestation->security_status === 'recorded_manually' && filled($attestation->security_reference) && $attestation->security_recorded_by && $attestation->security_recorded_at?->lte(now());
            $checks[] = new RequirementResult('identity_security_paperwork', 'إجراء إدارة الأمن الورقي', $recorded ? RequirementState::Met : RequirementState::NeedsReview, $recorded ? 'سجل الموظف مرجع مراجعة الإجراء الورقي إداريًا؛ ليس اعتمادًا آليًا أو ختمًا حكوميًا.' : 'يلزم إجراء إدارة الأمن الورقي المنفصل وتسجيل مرجعه؛ المصادقة التجريبية لا تستبدله.', mandatory: $required);
        }

        return $checks;
    }

    public function canAttest(User $actor, IdentityCardAttestation $attestation): bool
    {
        return self::enabled() && $actor->id !== $attestation->form->user_id
            && NeighborhoodOfficial::authorized($actor) && $attestation->authority_type === 'neighborhood'
            && (int) data_get($attestation->recipient_scope, 'employee_id') === $actor->id
            && $attestation->official?->user_id === $actor->id && $attestation->official?->can_attest
            && $attestation->official?->is_active;
    }

    public static function approvedForSubmission(?IdentityCardAttestation $attestation): bool
    {
        return self::enabled() && $attestation?->authority_type === 'neighborhood'
            && $attestation->status === 'approved' && $attestation->verified_at !== null
            && in_array($attestation->verification_source, ['local_code', 'local_fingerprint', 'local_employee_confirmation'], true);
    }
}
