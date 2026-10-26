<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class GovernmentService extends Model
{
    public const DOCUMENT_TYPES = ['identity' => 'البطاقة الشخصية', 'passport' => 'جواز السفر', 'driving_license' => 'رخصة القيادة'];

    public const OPERATIONS = ['issue' => 'إصدار', 'renewal' => 'تجديد', 'replacement' => 'بدل فاقد'];

    public const ALIASES = ['passports-first-issue' => 'passport-first-issue', 'passports-renewal' => 'passport-renewal', 'driving-first-issue' => 'license-first-issue', 'driving-renewal' => 'license-renewal'];

    protected $guarded = ['id', 'rules_version'];

    protected function casts(): array
    {
        return [
            'conditions' => 'array', 'required_documents' => 'array', 'required_fields' => 'array',
            'application_fields' => 'array',
            'verification_rules' => 'array',
            'workflow_rules' => 'array',
            'issuance_rules' => 'array', 'integration_rules' => 'array',
            'require_separate_approver' => 'boolean',
            'renewal_statuses' => 'array', 'terminal_request_statuses' => 'array',
            'fee' => 'decimal:2', 'is_active' => 'boolean', 'rules_approved' => 'boolean',
            'requires_driving_clearance' => 'boolean', 'minimum_age' => 'integer',
            'renewal_window_days' => 'integer', 'rules_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $service) {
            if ($service->isDirty(['code', 'document_type', 'operation'])) {
                throw ValidationException::withMessages(['code' => 'رمز الخدمة ونوع الوثيقة والعملية ثابتة لحماية الطلبات السابقة. أنشئ خدمة مستقلة عند تغييرها.']);
            }
            if ($service->isDirty(['conditions', 'required_documents', 'required_fields', 'application_fields', 'verification_rules', 'workflow_rules', 'issuance_rules', 'integration_rules', 'require_separate_approver', 'minimum_age', 'requires_driving_clearance', 'renewal_statuses', 'renewal_window_days', 'terminal_request_statuses', 'rules_approved', 'source_reference'])) {
                $service->rules_version = $service->getOriginal('rules_version') + 1;
            }
        });
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CitizenServiceRequest::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CitizenAttachment::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CitizenServiceAssessment::class);
    }

    public function appointmentSlots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RequestCharge::class);
    }

    public function codes(): array
    {
        return [$this->code, ...array_keys(self::ALIASES, $this->code, true)];
    }

    public function terminalStatuses(): array
    {
        $canonical = array_map(fn ($status) => $status->value, array_filter(ServiceRequestStatus::cases(), fn ($status) => $status->isTerminal()));
        $legacy = array_filter($this->terminal_request_statuses ?? [], fn ($status) => ServiceRequestStatus::tryFrom($status) === null);

        return array_values(array_unique([...$canonical, ...$legacy]));
    }

    public function submissionFingerprint(): string
    {
        return hash('sha256', json_encode($this->attributesToArray(), JSON_THROW_ON_ERROR));
    }

    public static function resolveCode(string $code): self
    {
        return self::where('code', self::ALIASES[$code] ?? $code)->firstOrFail();
    }

    public function publicUrl(): string
    {
        return route('services.catalog.show', $this->code);
    }
}
