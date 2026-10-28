<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityCardForm extends Model
{
    protected $guarded = ['*'];

    protected $attributes = ['revision' => 1];

    public const STATUSES = [
        'draft' => 'مسودة', 'awaiting_data' => 'بانتظار استكمال البيانات',
        'pending_photo_review' => 'بانتظار مراجعة الصورة', 'pending_attestation' => 'بانتظار المصادقة',
        'pending_fingerprint_attendance' => 'بانتظار الحضور لإجراء البصمة',
        'pending_workplace_attestation' => 'بانتظار مصادقة جهة العمل', 'pending_neighborhood_attestation' => 'بانتظار مصادقة عاقل الحارة',
        'pending_witness_review' => 'بانتظار حضور الشاهدين ومراجعة وثائقهما', 'pending_security_review' => 'بانتظار إجراء إدارة الأمن الورقي', 'cancelled' => 'ملغى',
        'pending_administrative_review' => 'بانتظار المراجعة الإدارية', 'rejected' => 'مرفوض', 'demo_completed' => 'اكتملت الإجراءات التجريبية للطلب',
        'fingerprint_failed' => 'تعذرت خطوة البصمة — يمكن إعادة المحاولة', 'returned_to_staff' => 'أعيد للموظف لاستكمال المراجعة',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'current_step' => 'integer', 'version' => 'integer', 'revision' => 'integer', 'declared_at' => 'datetime', 'submitted_at' => 'datetime', 'arrived_at' => 'datetime', 'identity_documents_checked_at' => 'datetime', 'correction_opened_at' => 'datetime', 'resubmitted_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function citizen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function application()
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function photo()
    {
        return $this->belongsTo(CitizenAttachment::class, 'citizen_attachment_id');
    }

    public function witnesses()
    {
        return $this->hasMany(IdentityCardWitness::class)->orderBy('position');
    }

    public function attestation()
    {
        return $this->hasOne(IdentityCardAttestation::class)->whereHas('form', fn ($query) => $query->whereColumn('identity_card_forms.revision', 'identity_card_attestations.form_revision'));
    }

    public function operations()
    {
        return $this->hasMany(IdentityFingerprintOperation::class)->orderByDesc('id');
    }

    public function administrativeDecisions()
    {
        return $this->hasMany(IdentityAdministrativeDecision::class)->orderByDesc('id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function correctionIsOpen(): bool
    {
        return $this->correction_opened_at && $this->submitted_at && $this->status === 'awaiting_data' && $this->current_step <= 6;
    }

    public function fingerprintStatusLabel(): string
    {
        return ['simulation_completed' => 'اكتملت محاكاة البصمة — ليست بصمة حيوية', 'simulation_failed' => 'فشلت محاكاة البصمة', 'manual_success' => 'نجاح جهاز مسجل إداريًا بواسطة الموظف', 'manual_failure' => 'فشل جهاز مسجل إداريًا بواسطة الموظف'][$this->fingerprint_status ?? ''] ?? 'لم تُسجل نتيجة للنسخة الحالية';
    }
}
