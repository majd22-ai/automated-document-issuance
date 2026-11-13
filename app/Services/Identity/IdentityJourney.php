<?php

namespace App\Services\Identity;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\IdentityAdministrativeDecision;
use App\Models\IdentityCardForm;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Operations\LockedRequest;
use App\Services\Oversight\AuditTrail;
use App\Services\Requirements\RequirementResult;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityJourney
{
    public static function applies(CitizenServiceRequest $request): bool
    {
        $form = $request->identityCardForm()->first();

        return $request->service_code === 'identity-first-issue' && $form?->journey_version === 1
            && $form->user_id === $request->user_id && $form->government_service_id === $request->government_service_id;
    }

    public function paid(CitizenServiceRequest $request): bool
    {
        $charge = $request->charge()->first();
        if (! $charge || ! $charge->invoice_number || $charge->user_id !== $request->user_id
            || $charge->government_service_id !== $request->government_service_id
            || $charge->amount !== $request->service_fee || $charge->currency !== $request->service_currency) {
            return false;
        }
        if ($charge->amount === '0.00' && $charge->status === 'not_required') {
            return true;
        }
        $receipt = $charge->postalCollection;

        return $charge->status === 'paid' && $receipt && $receipt->amount === $charge->amount && $receipt->currency === $charge->currency
            && $receipt->collected_at->lte(now()) && filled($receipt->receipt_reference) && $receipt->postal_collector_id && $receipt->postal_office_id;
    }

    public function assertScheduling(User $actor, CitizenServiceRequest $request): void
    {
        abort_unless($actor->role === 'employee' && $actor->canReviewService($request->government_service_id, 'can_review'), 403);
        if ($request->status !== 'accepted' || ! $this->paid($request) || $request->identityCardForm->arrived_at) {
            $this->fail('يجب القبول المبدئي وتأكيد السداد البريدي قبل تحديد موعد الحضور.');
        }
    }

    public function arrivalReasons(IdentityCardForm $form): array
    {
        $request = $form->application;
        $reasons = [];
        if (! in_array($request->status, ['accepted', 'approved'], true) || ! $this->paid($request)) {
            $reasons[] = 'يلزم قبول مبدئي وسداد بريدي مؤكد قبل الحضور.';
        }
        $booking = $request->appointments()->with('slot')->latest('id')->first();
        if (! $booking || ! in_array($booking->status, ['booked', 'confirmed', 'completed'], true)
            || $booking->user_id !== $request->user_id || $booking->government_service_id !== $request->government_service_id
            || ! $booking->slot || $booking->slot->government_service_id !== $request->government_service_id
            || (! $form->arrived_at && (! $booking->slot->is_active || ! now()->betweenIncluded($booking->slot->starts_at, $booking->slot->ends_at)))) {
            $reasons[] = 'يلزم موعد حضور صالح لهذا الطلب؛ تسجيل الحضور متاح خلال الفترة المحددة.';
        }

        return $reasons;
    }

    public function evidence(IdentityCardForm $form, string $kind)
    {
        return $form->visitEvidence()->where('form_revision', $form->revision)->where('kind', $kind)->latest('id')->first();
    }

    public function evidenceReasons(IdentityCardForm $form): array
    {
        $reasons = [];
        foreach (['blood_result' => 'نتيجة فحص الدم الخارجي', 'card_photo' => 'صورة الوجه المخصصة للبطاقة'] as $kind => $label) {
            $evidence = $this->evidence($form, $kind);
            $file = $evidence?->attachment;
            if (! $evidence || $evidence->status !== 'approved' || ! $evidence->reviewed_by || ! $evidence->reviewed_at?->lte(now())
                || ! $file || $file->user_id !== $form->user_id || $file->government_service_id !== $form->government_service_id
                || $file->citizen_service_request_id !== $form->citizen_service_request_id || ! $file->intact()) {
                $reasons[] = 'يلزم إرفاق ومراجعة وقبول '.$label.' للنسخة الحالية.';
            }
        }
        if ($form->witness_attendance_reason && $form->witnesses()->where(fn ($q) => $q->where('attendance_status', '!=', 'present')->orWhere('verification_status', '!=', 'approved')->orWhereNull('reviewed_by'))->exists()) {
            $reasons[] = 'يلزم استكمال حضور الشاهدين الذي طلبه الموظف بسبب مسجل.';
        }

        return $reasons;
    }

    public function checks(CitizenServiceRequest $request, ?ServiceRequestStatus $target): array
    {
        $final = in_array($target?->value ?? $request->status, ['approved', 'processing', 'completed'], true);
        $form = $request->identityCardForm;
        $reasons = array_merge($this->arrivalReasons($form), $this->evidenceReasons($form));
        if (! $form->arrived_at || ! $form->received_by) {
            $reasons[] = 'لم يسجل حضور المواطن.';
        }

        return [new RequirementResult('identity_visit', 'السداد وزيارة الهوية', $reasons ? RequirementState::Unmet : RequirementState::Met,
            $reasons ? implode(' ', $reasons) : 'اكتملت أدلة الزيارة والسداد.', mandatory: $final)];
    }

    public function notify(User $recipient, CitizenServiceRequest $request, string $key, string $title, string $body): void
    {
        $recipient->notifyNow(new CitizenActivity('identity-journey:'.$key, ['kind' => 'request_activity', 'title' => $title, 'body' => $body,
            'request_reference' => $request->reference, 'occurred_at' => now()->toIso8601String()], $request->id));
    }

    public function openCorrection(CitizenServiceRequest $request, User $actor, string $reason): void
    {
        $form = IdentityCardForm::whereKey($request->identityCardForm->id)->lockForUpdate()->firstOrFail();
        abort_if($request->issuedDocument()->exists(), 409);
        $request->appointments()->whereIn('status', ['booked', 'confirmed'])->update(['status' => 'cancelled', 'active_request_key' => null]);
        if ($form->correctionIsOpen()) {
            return;
        }
        $revision = $form->revision + 1;
        (new IdentityAdministrativeDecision)->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $revision,
            'action' => 'correction_required', 'expected_version' => $form->version, 'decision_token' => (string) Str::uuid(),
            'decided_by' => $actor->id, 'decided_at' => now(), 'citizen_reason' => $reason])->save();
        $form->forceFill(['status' => 'awaiting_data', 'current_step' => 1, 'revision' => $revision, 'version' => $form->version + 1,
            'correction_opened_at' => now(), 'correction_opened_by' => $actor->id, 'correction_reason' => $reason,
            'arrived_at' => null, 'received_by' => null, 'identity_documents_checked_at' => null, 'arrival_reference' => null, 'fingerprint_status' => null])->save();
        $request->appointments()->whereIn('status', ['booked', 'confirmed'])->update(['status' => 'cancelled', 'active_request_key' => null]);
    }

    public function issuanceFailed(CitizenServiceRequest $request, User $actor, string $token, string $reason): void
    {
        app(LockedRequest::class)->run($actor, $request, 'issueDocument', function ($actor, $owner, $service, $request) use ($token, $reason) {
            if ($request->status !== 'approved' || $request->issuedDocument()->exists()) {
                return;
            }
            app(AuditTrail::class)->recordPrivate('identity_issue_failed', CitizenServiceRequest::class, $request->id, $actor,
                ['status' => 'approved', 'action' => 'copy_generation_failed'], $reason, result: 'failed');
            $this->notify($owner, $request, 'issue-failed:'.$token, 'تعذر إصدار الوثيقة', 'تعذر تجهيز النسخة الإلكترونية؛ ما زال طلبك معتمدًا وبانتظار معالجة الموظف وإعادة المحاولة. لم يصدر أو يسلم سجل وثيقة.');
        });
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['identity_journey' => $message]);
    }
}
