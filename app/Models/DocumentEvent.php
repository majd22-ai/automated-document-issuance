<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEvent extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['operation_token', 'command_hash', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function document()
    {
        return $this->belongsTo(CitizenDocument::class, 'citizen_document_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return ['issued' => 'إصدار سجل الوثيقة', 'renewed' => 'تجديد الوثيقة السابقة', 'copy_regenerated' => 'إعادة إنشاء النسخة الإلكترونية', 'delivery_selected' => 'اختيار طريقة الاستلام', 'delivery_status' => 'تحديث التسليم', 'completed' => 'إكمال الطلب'][$this->action] ?? $this->action;
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Document audit history is immutable'));
        static::deleting(fn () => throw new \LogicException('Document audit history is retained'));
    }
}
