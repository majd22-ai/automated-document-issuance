<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitizenAppointment extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['active_request_key'];

    public function citizen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function application()
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function service()
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function slot()
    {
        return $this->belongsTo(AppointmentSlot::class, 'appointment_slot_id');
    }

    public function statusLabel(): string
    {
        return ['booked' => 'محجوز', 'confirmed' => 'مؤكد', 'completed' => 'مكتمل', 'cancelled' => 'ملغى', 'no_show' => 'لم يحضر'][$this->status] ?? 'غير معروف';
    }
}
