<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestDeficiency extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['resolution_note'];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }

    public function application()
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function decision()
    {
        return $this->belongsTo(ReviewDecision::class, 'review_decision_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function label(): string
    {
        return ['open' => 'مطلوب استكماله', 'awaiting_review' => 'بانتظار تحقق الموظف', 'resolved' => 'تم التحقق من استكماله'][$this->status] ?? 'غير معروف';
    }

    protected static function booted(): void
    {
        static::updating(function (self $row) {
            if ($row->isDirty(['citizen_service_request_id', 'review_decision_id', 'target_type', 'target_key', 'description'])) {
                throw new \LogicException('Original deficiency is immutable');
            }
        });
        static::deleting(fn () => throw new \LogicException('Deficiency history is retained'));
    }
}
