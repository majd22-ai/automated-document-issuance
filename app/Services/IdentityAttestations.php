<?php

namespace App\Services;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
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

        return IdentityCardAttestation::whereHas('form', function ($query) use ($actor) {
            $query->where('user_id', '!=', $actor->id);
            if ($actor->role !== 'admin') {
                $query->whereIn('government_service_id', $actor->reviewPermissions()->where('can_review', true)->pluck('government_service_id'));
            }
        });
    }

    private function lock(IdentityCardForm $form): IdentityCardForm
    {
        // Same owner -> service -> request order as the existing review/submission services.
        User::whereKey($form->user_id)->lockForUpdate()->firstOrFail();
        GovernmentService::whereKey($form->government_service_id)->lockForUpdate()->firstOrFail();
        $application = CitizenServiceRequest::whereKey($form->citizen_service_request_id)->lockForUpdate()->firstOrFail();
        abort_unless(in_array($application->status, ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved'], true), 409, 'حالة الطلب الحالية لا تسمح بإجراء مصادقة.');
        $form = IdentityCardForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
        abort_unless($form->submitted_at && $form->government_service_id === $application->government_service_id && $form->user_id === $application->user_id, 409);

        return $form;
    }

    public function choose(User $actor, IdentityCardForm $form, array $data): IdentityCardAttestation
    {
        return DB::transaction(function () use ($actor, $form, $data) {
            $form = $this->lock($form);
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('chooseAttestation', $form);
            $existing = $form->attestation()->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->authority_type === $data['authority_type'], 409, 'تم اختيار مسار المصادقة لهذا الطلب بالفعل.');

                return $existing;
            }
            $type = $data['authority_type'];
            $security = (bool) config('identity_card.attestation_security.'.$type, false);
            $attestation = new IdentityCardAttestation;
            $attestation->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $form->revision, 'citizen_name' => $form->data['applicant_name'] ?? null, 'reference' => 'SIM-ATT-'.Str::ulid(),
                'authority_type' => $type, 'recipient_name' => $type === 'witnesses' ? 'الشاهدان — مراجعة الموظف المختص' : $data['recipient_name'],
                'status' => 'pending', 'created_by' => $actor->id, 'notification_created_at' => $type === 'witnesses' ? null : now(),
                'security_required' => $security, 'security_status' => $security ? 'pending' : 'not_required',
            ])->save();
            $form->forceFill(['status' => match ($type) {
                'workplace' => 'pending_workplace_attestation', 'neighborhood' => 'pending_neighborhood_attestation', 'witnesses' => 'pending_witness_review',
            }, 'version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_attestation_requested', IdentityCardAttestation::class, $attestation->id, $actor, changes: ['status' => 'pending', 'action' => $type]);

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
        return DB::transaction(function () use ($actor, $attestation, $data) {
            $form = $this->lock($attestation->form);
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('simulate', $form);
            $row = IdentityCardAttestation::whereKey($attestation->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->form_revision === $form->revision, 409, 'المصادقة تخص نسخة سابقة من الاستمارة.');
            abort_unless($row->status === 'pending', 409, 'سبق اتخاذ قرار أو إلغاء المصادقة؛ لا يمكن تكراره.');
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
            $form->forceFill(['status' => ! $approved ? 'rejected' : ($row->security_required ? 'pending_security_review' : 'pending_fingerprint_attendance'), 'version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_attestation_decided', IdentityCardAttestation::class, $row->id, $actor, changes: ['status' => $row->status, 'action' => $row->authority_type]);
            $form->citizen->notifyNow(new CitizenActivity('identity-attestation:'.$row->id.':decided', [
                'kind' => 'request_activity', 'title' => 'تحديث المصادقة التجريبية',
                'body' => $approved ? 'تمت المصادقة التجريبية داخل المنصة. راجع الطلب لمعرفة الإجراء التالي؛ ليست اعتمادًا حكوميًا.' : 'لم تُقبل المصادقة التجريبية. راجع استمارة الطلب للاطلاع على السبب.',
                'request_reference' => $form->application->reference, 'occurred_at' => now()->toIso8601String(),
            ], $form->citizen_service_request_id));

            return $row;
        });
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
}
