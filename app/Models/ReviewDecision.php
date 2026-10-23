<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewDecision extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['internal_notes', 'command_hash', 'decision_token', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array', 'requirements_result' => 'array', 'review_version' => 'integer', 'decided_at' => 'immutable_datetime'];
    }

    public function application()
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function deficiencies()
    {
        return $this->hasMany(RequestDeficiency::class);
    }

    public function label(): string
    {
        return ['start' => 'بدء المراجعة', 'accept' => 'قبول بعد المراجعة', 'return' => 'إعادة للاستكمال', 'reject' => 'رفض نهائي', 'approve' => 'اعتماد تمهيدًا للإصدار', 'resubmit' => 'إعادة تقديم بعد الاستكمال', 'assign' => 'إسناد الطلب', 'resolve' => 'التحقق من استكمال نقص', 'processing' => 'بدء التجهيز'][$this->action] ?? $this->action;
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Review history is immutable'));
        static::deleting(fn () => throw new \LogicException('Review history is retained'));
    }
}
