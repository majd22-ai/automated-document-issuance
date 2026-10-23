<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalExamination extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['private_notes', 'source_reference'];

    protected function casts(): array
    {
        return ['examined_at' => 'immutable_date', 'expires_at' => 'immutable_date', 'recorded_at' => 'immutable_datetime', 'rules_version' => 'integer'];
    }

    public function application()
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function citizen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function appointment()
    {
        return $this->belongsTo(CitizenAppointment::class, 'citizen_appointment_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function resultLabel(): string
    {
        return ['passed' => 'ناجح', 'failed' => 'غير ناجح', 'pending' => 'قيد الانتظار', 'retest' => 'يحتاج إلى إعادة فحص'][$this->result] ?? 'غير معروف';
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Examination history is append-only'));
        static::deleting(fn () => throw new \LogicException('Examination history is retained'));
    }
}
