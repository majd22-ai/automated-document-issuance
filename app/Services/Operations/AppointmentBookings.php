<?php

namespace App\Services\Operations;

use App\Models\AppointmentSlot;
use App\Models\CitizenAppointment;
use App\Models\CitizenServiceRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AppointmentBookings
{
    public function __construct(private LockedRequest $locks) {}

    public function book(User $actor, CitizenServiceRequest $request, int $slotId): CitizenAppointment
    {
        return $this->locks->run($actor, $request, 'operate', function ($actor, $owner, $service, $request) use ($slotId) {
            if ($request->appointments()->whereNotNull('active_request_key')->exists()) {
                $this->fail('يوجد موعد قائم لهذا الطلب. استخدم إعادة الجدولة.');
            }
            $slot = AppointmentSlot::whereKey($slotId)->lockForUpdate()->first();

            return $this->reserve($actor, $owner, $service, $request, $slot);
        });
    }

    private function reserve($actor, $owner, $service, $request, ?AppointmentSlot $slot): CitizenAppointment
    {
        if (! $service->is_active || ! $service->rules_approved || ! $slot || $slot->government_service_id !== $service->id || ! $slot->available()) {
            $this->fail('الموعد غير متاح أو ممتلئ أو لا يخص هذه الخدمة.');
        }
        $booking = (new CitizenAppointment)->forceFill(['user_id' => $owner->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'appointment_slot_id' => $slot->id, 'status' => 'booked', 'active_request_key' => $request->id, 'recorded_by' => $actor->id]);
        $booking->save();
        $this->locks->invalidate($request);
        $this->locks->audit('appointment_booked', $booking->id, $request, $actor);

        return $booking;
    }

    public function change(User $actor, CitizenAppointment $booking, string $action, ?int $slotId = null): void
    {
        $this->locks->run($actor, $booking->application, 'operate', function ($actor, $owner, $service, $request) use ($booking, $action, $slotId) {
            // The service lock serializes all slots of this service; keep slot ordering stable too.
            $slots = AppointmentSlot::whereIn('id', array_filter([$booking->appointment_slot_id, $slotId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = CitizenAppointment::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $slot = $slots->get($current->appointment_slot_id);
            if (! in_array($current->status, ['booked', 'confirmed'], true) || ! $slot) {
                $this->fail('لا يمكن تعديل هذا الموعد بعد انتهاء حالته.');
            }
            if (in_array($action, ['cancel', 'reschedule'], true)) {
                if (! $slot->starts_at->isFuture()) {
                    $this->fail('انتهى وقت الإلغاء أو إعادة الجدولة.');
                }
                $current->forceFill(['status' => 'cancelled', 'active_request_key' => null])->save();
                if ($action === 'reschedule') {
                    $this->reserve($actor, $owner, $service, $request, $slots->get($slotId));
                }
            } else {
                if ($actor->role !== 'employee') {
                    abort(403);
                }
                if (($action === 'confirmed' && ($current->status !== 'booked' || ! $slot->starts_at->isFuture()))
                    || (in_array($action, ['completed', 'no_show'], true) && $slot->starts_at->isFuture())
                    || ! in_array($action, ['confirmed', 'completed', 'no_show'], true)) {
                    $this->fail('الانتقال المطلوب لحالة الموعد غير مسموح.');
                }
                $current->forceFill(['status' => $action, 'active_request_key' => in_array($action, ['completed', 'no_show'], true) ? null : $request->id])->save();
            }
            $this->locks->invalidate($request);
            $this->locks->audit('appointment_'.$action, $current->id, $request, $actor);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['appointment' => $message]);
    }
}
