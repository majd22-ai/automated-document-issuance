<?php

namespace App\Services\Operations;

use App\Models\CitizenServiceRequest;
use App\Models\MedicalExamination;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecordExamination
{
    public function __construct(private LockedRequest $locks) {}

    public function record(User $actor, CitizenServiceRequest $application, array $data): MedicalExamination
    {
        return $this->locks->run($actor, $application, 'recordOperations', function ($actor, $owner, $service, $request) use ($data) {
            if ((int) $data['expected_rules_version'] !== $service->rules_version) {
                throw ValidationException::withMessages(['examination' => 'تغيرت قواعد الفحص منذ فتح النموذج. راجع القواعد الحالية وحدّث الصفحة قبل تسجيل النتيجة.']);
            }
            if ($service->document_type !== 'driving_license' || ($service->verification_rules['medical']['required'] ?? false) !== true) {
                throw ValidationException::withMessages(['examination' => 'هذه الخدمة لا تشترط الفحص الطبي المسجل.']);
            }
            $latest = $request->examinations()->latest('id')->first();
            if ((int) $data['expected_id'] !== ($latest?->id ?? 0)) {
                throw ValidationException::withMessages(['examination' => 'تغير سجل الفحص منذ فتح الصفحة. حدّثها قبل إدخال نتيجة جديدة.']);
            }
            if ($data['result'] === 'passed' && ($service->verification_rules['medical']['requires_expiry'] ?? false) && empty($data['expires_at'])) {
                throw ValidationException::withMessages(['expires_at' => 'تاريخ الانتهاء الموثق مطلوب لهذه الخدمة.']);
            }
            $booking = null;
            if (! empty($data['citizen_appointment_id'])) {
                $booking = $request->appointments()->find($data['citizen_appointment_id']);
                if (! $booking || ! in_array($booking->status, ['confirmed', 'completed'], true) || $booking->slot->starts_at->isFuture()) {
                    throw ValidationException::withMessages(['citizen_appointment_id' => 'الموعد لا يخص الطلب أو لم يحن وقت الفحص المؤكد.']);
                }
            }
            $exam = (new MedicalExamination)->forceFill(['user_id' => $owner->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'citizen_appointment_id' => $booking?->id, 'rules_version' => $service->rules_version, 'result' => $data['result'], 'examined_at' => $data['examined_at'] ?? null, 'expires_at' => $data['expires_at'] ?? null, 'source_reference' => $data['source_reference'], 'private_notes' => $data['private_notes'] ?? null, 'recorded_by' => $actor->id, 'recorded_at' => now()]);
            $exam->save();
            $this->locks->invalidate($request);
            $this->locks->audit('medical_result_recorded', $exam->id, $request, $actor);

            return $exam;
        });
    }
}
