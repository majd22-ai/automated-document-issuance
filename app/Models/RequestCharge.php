<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestCharge extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
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

    public function payment()
    {
        return $this->hasOne(ManualPayment::class);
    }

    public function statusLabel(): string
    {
        return ['due' => 'مستحقة', 'paid' => 'مدفوعة — تسجيل يدوي موثق', 'not_required' => 'رسوم صفرية مسجلة'][$this->status] ?? 'غير معروف';
    }

    protected static function booted(): void
    {
        static::updating(function (self $charge) {
            if ($charge->isDirty(['user_id', 'government_service_id', 'citizen_service_request_id', 'amount', 'currency', 'source_reference'])) {
                throw new \LogicException('Charge snapshot is immutable');
            }
        });
        static::deleting(fn () => throw new \LogicException('Financial history is retained'));
    }
}
