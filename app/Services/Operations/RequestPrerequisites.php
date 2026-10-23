<?php

namespace App\Services\Operations;

use App\Enums\RequirementState as State;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Services\Requirements\RequirementResult;

class RequestPrerequisites
{
    public const STAGES = ['accepted', 'approved', 'processing'];

    private const RANK = ['submitted' => 0, 'accepted' => 1, 'approved' => 2, 'processing' => 3];

    public function rules(GovernmentService $service, ?CitizenServiceRequest $request): array
    {
        $rules = $request ? ($request->service_snapshot['workflow_rules'] ?? []) : ($service->workflow_rules ?? []);

        return is_array($rules) ? $rules : ['invalid' => true];
    }

    private function due(string $stage, ?CitizenServiceRequest $request, ?ServiceRequestStatus $target): bool
    {
        return $request && ($target === null || (self::RANK[$target->value] ?? -1) >= (self::RANK[$stage] ?? PHP_INT_MAX));
    }

    public function medicalDue(GovernmentService $service, ?CitizenServiceRequest $request, ?ServiceRequestStatus $target): bool
    {
        $stage = $this->rules($service, $request)['medical_stage'] ?? null;

        // Preserve requirement5 behavior without an explicit, valid later-stage policy.
        return ! in_array($stage, self::STAGES, true) || $this->due($stage, $request, $target);
    }

    public function checks(GovernmentService $service, ?CitizenServiceRequest $request, ?ServiceRequestStatus $target): array
    {
        $rules = $this->rules($service, $request);
        $valid = ! array_diff(array_keys($rules), ['appointment', 'medical_stage', 'payment']);
        foreach (['appointment', 'payment'] as $kind) {
            if (array_key_exists($kind, $rules)) {
                $rule = $rules[$kind];
                $valid = $valid && is_array($rule) && is_bool($rule['required'] ?? null) && in_array($rule['stage'] ?? null, self::STAGES, true);
                if ($valid && $kind === 'appointment') {
                    $valid = is_array($rule['statuses'] ?? null) && $rule['statuses'] && ! array_diff($rule['statuses'], ['booked', 'confirmed', 'completed']);
                }
            }
        }
        if (isset($rules['medical_stage'])) {
            $valid = $valid && in_array($rules['medical_stage'], self::STAGES, true);
        }
        if (! $valid) {
            return [new RequirementResult('operations_configuration', 'قواعد المواعيد والفحص والسداد', State::Unverifiable, 'قواعد مراحل الخدمة غير مكتملة أو غير صالحة؛ راجع الإدارة.')];
        }
        $checks = [];
        if ($rules['appointment']['required'] ?? false) {
            $rule = $rules['appointment'];
            $due = $this->due($rule['stage'], $request, $target);
            $booking = $request?->appointments()->latest('id')->first();
            $met = $booking && $booking->user_id === $request->user_id && $booking->government_service_id === $service->id
                && in_array($booking->status, $rule['statuses'], true) && $booking->slot
                && ($booking->status === 'completed' || $booking->slot->is_active)
                && $booking->slot->government_service_id === $service->id
                && ($booking->status === 'completed' || $booking->slot->starts_at->isFuture());
            $checks[] = new RequirementResult('appointment', 'موعد الخدمة', $met ? State::Met : State::Unmet, $met ? 'الموعد يستوفي حالة الخدمة المسجلة.' : 'يلزم موعد بالحالة المعتمدة قبل مرحلة '.$rule['stage'].'.', 'citizen', $due);
        }
        $amount = $request ? $request->service_fee : $service->fee;
        $required = $rules['payment']['required'] ?? ($amount !== null && RequestCharges::money($amount) !== '0.00');
        if ($required) {
            $due = $this->due($rules['payment']['stage'] ?? 'processing', $request, $target);
            $charge = $request?->charge;
            $receipt = $charge?->payment;
            $zero = $charge && $charge->amount === '0.00' && $charge->status === 'not_required';
            $paid = $charge && $charge->status === 'paid' && $receipt && $receipt->amount === $charge->amount && $receipt->currency === $charge->currency && $receipt->recorded_by && $receipt->recorded_at->lte(now()) && $receipt->paid_at->lte(today());
            $met = ($zero || $paid) && $charge->user_id === $request->user_id && $charge->government_service_id === $service->id && $charge->amount === $request->service_fee && $charge->currency === $request->service_currency;
            $checks[] = new RequirementResult('payment', 'سداد رسوم الطلب', $met ? State::Met : ($amount === null ? State::Unverifiable : State::Unmet), $met ? 'تم استيفاء السداد أو الرسوم الصفرية المسجلة.' : 'السداد غير مكتمل أو بيانات الرسوم غير متوفرة؛ راجع تفاصيل رسوم الطلب.', 'staff', $due);
        }

        return $checks;
    }
}
