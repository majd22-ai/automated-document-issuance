<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityCardAttestation extends Model
{
    protected $guarded = ['*'];

    public const AUTHORITIES = ['workplace' => 'جهة العمل', 'neighborhood' => 'عاقل الحارة', 'witnesses' => 'الشاهدان'];

    public const STATUSES = ['pending' => 'بانتظار القرار', 'approved' => 'تمت المصادقة التجريبية', 'rejected' => 'مرفوض', 'cancelled' => 'ملغى'];

    protected function casts(): array
    {
        return ['recipient_scope' => 'array', 'verification_attempts' => 'integer', 'verified_at' => 'datetime', 'notification_created_at' => 'datetime', 'notification_read_at' => 'datetime', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime', 'security_required' => 'boolean', 'form_revision' => 'integer', 'security_recorded_at' => 'datetime'];
    }

    public function official()
    {
        return $this->belongsTo(NeighborhoodOfficial::class, 'neighborhood_official_id');
    }

    public function authorityLabel(): string
    {
        return self::AUTHORITIES[$this->authority_type] ?? $this->authority_type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function form()
    {
        return $this->belongsTo(IdentityCardForm::class, 'identity_card_form_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
