<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityFingerprintOperation extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['operation_token', 'input_fingerprint', 'success_key'];

    public const SOURCES = ['simulation' => 'محاكاة تجريبية — ليست بصمة حيوية', 'administrative_real_device' => 'تسجيل إداري لنتيجة جهاز حقيقي — أدخلها الموظف وليست واردة آليًا', 'trusted_integration' => 'تكامل حقيقي موثوق (غير موصول حاليًا)'];

    protected function casts(): array
    {
        return ['executed_at' => 'datetime', 'form_revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Fingerprint operation history is immutable; create a new operation.'));
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? 'مصدر غير معروف';
    }

    public function resultLabel(): string
    {
        return $this->result === 'success' ? ($this->source === 'simulation' ? 'نجحت المحاكاة' : 'نجاح مسجل إداريًا') : ($this->source === 'simulation' ? 'فشلت المحاكاة' : 'فشل مسجل إداريًا');
    }

    public function form()
    {
        return $this->belongsTo(IdentityCardForm::class, 'identity_card_form_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
