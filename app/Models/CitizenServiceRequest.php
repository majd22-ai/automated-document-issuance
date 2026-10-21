<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Services\ServiceEligibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CitizenServiceRequest extends Model
{
    protected $fillable = ['reference', 'service_code', 'status'];

    protected $hidden = ['submission_token', 'active_request_key'];

    private bool $transitioning = false;

    public static function activeKey(int $userId, int $serviceId, string $operation): string
    {
        return hash('sha256', $userId.':'.$serviceId.':'.$operation);
    }

    public function statusLabel(): string
    {
        return ServiceRequestStatus::tryFrom($this->status ?? '')?->label() ?? ($this->status ?: 'غير متوفرة');
    }

    public function transitionTo(ServiceRequestStatus $next, User $actor, ?string $visibleReason = null): self
    {
        Gate::forUser($actor)->authorize('transition', $this);

        return DB::transaction(function () use ($next, $visibleReason, $actor) {
            $citizen = User::whereKey($this->user_id)->lockForUpdate()->firstOrFail();
            $service = GovernmentService::whereKey($this->government_service_id)->lockForUpdate()->first();
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->fresh())->authorize('transition', $locked);
            $current = ServiceRequestStatus::tryFrom($locked->status ?? '');
            if (! $current?->canTransitionTo($next) || $next === ServiceRequestStatus::Completed) {
                throw ValidationException::withMessages(['status' => 'الانتقال غير مسموح أو يحتاج تكامل إصدار الوثائق قبل الاكتمال.']);
            }
            if (in_array($next, [ServiceRequestStatus::Rejected, ServiceRequestStatus::RequiresCorrection], true) && ! filled($visibleReason)) {
                throw ValidationException::withMessages(['citizen_visible_reason' => 'سبب النقص أو الرفض المسموح بعرضه مطلوب.']);
            }
            if (in_array($next, [ServiceRequestStatus::Submitted, ServiceRequestStatus::Accepted, ServiceRequestStatus::Approved, ServiceRequestStatus::Processing], true)) {
                if (! $service) {
                    throw ValidationException::withMessages(['requirements' => 'الخدمة المرتبطة غير متاحة للتحقق.']);
                }
                $eligibility = app(ServiceEligibility::class);
                $report = $eligibility->evaluate($citizen, $service, $locked);
                $eligibility->audit($report, $citizen, $service, $locked);
                if (! $report->eligible()) {
                    throw ValidationException::withMessages(['requirements' => $report->reasons()]);
                }
                $locked->requirements_result = $report->toArray();
                $locked->requirements_checked_at = now();
            }
            $locked->transitioning = true;
            try {
                $locked->status = $next->value;
                $locked->citizen_visible_reason = $visibleReason;
                $locked->active_request_key = $next->isTerminal() ? null : self::activeKey($locked->user_id, $locked->government_service_id, $locked->request_type);
                $locked->save();
            } finally {
                $locked->transitioning = false;
            }

            return $locked;
        });
    }

    protected function casts(): array
    {
        return ['service_fee' => 'decimal:2', 'service_rules_version' => 'integer', 'service_snapshot' => 'array', 'request_data' => 'array', 'declared_at' => 'datetime', 'submitted_at' => 'datetime', 'requirements_result' => 'array', 'requirements_checked_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CitizenAttachment::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $service = GovernmentService::whereIn('code', [GovernmentService::ALIASES[$request->service_code] ?? $request->service_code])->first();
            if ($service) {
                $request->government_service_id = $service->id;
                $request->service_name = $service->name;
                $request->service_fee = $service->fee;
                $request->service_currency = $service->currency;
                $request->service_rules_version = $service->rules_version;
                $request->service_snapshot = $service->only(['code', 'name', 'document_type', 'operation', 'description', 'conditions', 'required_documents', 'required_fields', 'application_fields', 'verification_rules', 'fee', 'currency', 'estimated_duration', 'rules_version', 'minimum_age', 'renewal_statuses', 'renewal_window_days', 'requires_driving_clearance', 'terminal_request_statuses']);
            }
        });
        static::updating(function (self $request) {
            if ($request->isDirty('status') && ! $request->transitioning) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تعديل حالة الطلب مباشرة. استخدم آلية الانتقال المخوّلة.']);
            }
            if ($request->getOriginal('submission_token') && $request->isDirty(['user_id', 'government_service_id', 'service_code', 'request_type', 'reference', 'submission_token', 'service_snapshot', 'service_name', 'service_fee', 'service_currency', 'service_rules_version', 'request_data', 'notes', 'declared_at', 'submitted_at'])) {
                throw ValidationException::withMessages(['request' => 'هوية الطلب وبيانات الخدمة التاريخية ثابتة بعد التقديم.']);
            }
            if ($request->getOriginal('submission_token') && ! $request->transitioning && $request->isDirty('active_request_key')) {
                throw ValidationException::withMessages(['request' => 'لا يمكن تغيير قفل الطلب القائم خارج آلية الانتقال المخوّلة.']);
            }
        });
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
