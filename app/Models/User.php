<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CitizenDocument::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CitizenAttachment::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(CitizenServiceRequest::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(CitizenAppointment::class);
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(MedicalExamination::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RequestCharge::class);
    }

    public function reviewPermissions(): HasMany
    {
        return $this->hasMany(ServiceReviewPermission::class);
    }

    public function activationRequest()
    {
        return $this->hasOne(CitizenActivationRequest::class);
    }

    public function canReviewService(?int $serviceId, string $ability): bool
    {
        return $this->is_active && $this->role === 'employee' && $serviceId && in_array($ability, ['can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate'], true)
            && $this->reviewPermissions()->where('government_service_id', $serviceId)->where($ability, true)->exists();
    }

    public function missingCitizenFields(): array
    {
        $missing = [];
        foreach (['name' => 'الاسم الكامل', 'username' => 'الرقم الوطني', 'email' => 'البريد الإلكتروني'] as $field => $label) {
            if (trim((string) $this->{$field}) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** Legacy accounts used username as national ID. New accounts keep them strictly separate. */
    public function nationalIdentity(): ?string
    {
        return $this->national_id ?: ($this->registration_status === null ? $this->username : null);
    }

    public function allowsCitizenService(GovernmentService $service): bool
    {
        if ($this->registration_status === null) {
            return true; // Preserve legacy per-service assessment policy; do not claim global verification.
        }

        return $this->registration_status === 'initial_account' ? $service->code === 'identity-first-issue' : $this->hasActivatedIdentity();
    }

    public function hasActivatedIdentity(): bool
    {
        return $this->registration_status === 'active' && $this->account_activated_at?->lte(now()) && $this->identity_verified_at?->lte(now()) && filled($this->national_id);
    }

    public function needsCitizenActivation(): bool
    {
        return $this->role === 'citizen' && $this->registration_status !== null && $this->registration_status !== 'initial_account' && ! $this->hasActivatedIdentity();
    }

    public function accountStatusLabel(): string
    {
        return match ($this->registration_status) {
            'initial_account' => 'حساب أولي — إصدار بطاقة لأول مرة فقط',
            'review_required' => 'الحساب يحتاج إلى مراجعة آمنة لدى الجهة المختصة',
            'active' => $this->hasActivatedIdentity() ? 'حساب مفعّل وهوية مؤكدة بإجراء إداري موثق' : 'حساب مفعّل — توثيق الهوية لم يكتمل',
            null => 'حساب سابق — يخضع للتحقق المعتمد لكل خدمة',
            default => 'بانتظار الحضور والتحقق والتفعيل',
        };
    }

    public function accountConfirmations(): HasMany
    {
        return $this->hasMany(CitizenAccountConfirmation::class);
    }

    public const ROLES = ['citizen', 'employee', 'admin'];

    protected $attributes = ['role' => 'citizen', 'is_active' => true];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_export_reports' => 'boolean',
            'last_login_at' => 'datetime',
            'auth_version' => 'integer',
            'phone_verified_at' => 'datetime',
            'account_activated_at' => 'datetime',
            'identity_verified_at' => 'datetime',
        ];
    }
}
