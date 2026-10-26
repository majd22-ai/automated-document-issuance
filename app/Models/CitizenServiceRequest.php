<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Services\Issuance\IssuanceLifecycle;
use App\Services\Review\ReviewActions;
use App\Services\Review\ReviewDeficiencies;
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

    private bool $correcting = false;

    public static function activeKey(int $userId, int $serviceId, string $operation): string
    {
        return hash('sha256', $userId.':'.$serviceId.':'.$operation);
    }

    public function statusLabel(): string
    {
        return ServiceRequestStatus::tryFrom($this->status ?? '')?->label() ?? ($this->status ?: 'غير متوفرة');
    }

    public function transitionTo(ServiceRequestStatus $next, User $actor, ?string $visibleReason = null, array $context = []): self
    {
        Gate::forUser($actor)->authorize('transition', [$this, $next]);

        return DB::transaction(function () use ($next, $visibleReason, $actor, $context) {
            $citizen = User::whereKey($this->user_id)->lockForUpdate()->firstOrFail();
            $service = GovernmentService::whereKey($this->government_service_id)->lockForUpdate()->first();
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('transition', [$locked, $next]);
            $actions = app(ReviewActions::class);
            $action = $actions->action($next);
            if ($actions->replay($locked, $actor, $action, $visibleReason, $context)) {
                return $locked;
            }
            $actions->assertCurrent($locked, $context);
            $current = ServiceRequestStatus::tryFrom($locked->status ?? '');
            if (! $current?->canTransitionTo($next)) {
                throw ValidationException::withMessages(['status' => 'الانتقال غير مسموح أو يحتاج تكامل إصدار الوثائق قبل الاكتمال.']);
            }
            if (in_array($next, [ServiceRequestStatus::Processing, ServiceRequestStatus::Completed], true)) {
                app(IssuanceLifecycle::class)->assertTransition($locked, $next);
            }
            if (in_array($next, [ServiceRequestStatus::Rejected, ServiceRequestStatus::RequiresCorrection], true) && ! filled($visibleReason)) {
                throw ValidationException::withMessages(['citizen_visible_reason' => 'سبب النقص أو الرفض المسموح بعرضه مطلوب.']);
            }
            if ($next === ServiceRequestStatus::RequiresCorrection) {
                app(ReviewDeficiencies::class)->validateReturn($locked, $context['deficiencies'] ?? []);
            }
            if (in_array($next, [ServiceRequestStatus::Accepted, ServiceRequestStatus::Approved], true)) {
                app(ReviewDeficiencies::class)->assertResolved($locked);
            }
            if ($next === ServiceRequestStatus::Approved && ! $locked->reviewDecisions()->where('action', 'accept')->exists()) {
                throw ValidationException::withMessages(['review' => 'لا يوجد قرار قبول موثق يسبق الاعتماد.']);
            }
            if ($next === ServiceRequestStatus::Submitted) {
                $locked->original_request_data ??= $locked->request_data ?? [];
                $locked->request_data = app(ReviewDeficiencies::class)->correctionData($locked, $context);
                $locked->correcting = true;
            }
            $report = null;
            if (in_array($next, [ServiceRequestStatus::Submitted, ServiceRequestStatus::Accepted, ServiceRequestStatus::Approved], true)) {
                if (! $service) {
                    throw ValidationException::withMessages(['requirements' => 'الخدمة المرتبطة غير متاحة للتحقق.']);
                }
                $eligibility = app(ServiceEligibility::class);
                // Final review approval must satisfy even prerequisites deferred to processing.
                $report = $eligibility->evaluate($citizen, $service, $locked, $next === ServiceRequestStatus::Approved ? ServiceRequestStatus::Processing : $next);
                $eligibility->audit($report, $citizen, $service, $locked);
                if (! $report->eligible()) {
                    throw ValidationException::withMessages(['requirements' => $report->reasons()]);
                }
                $locked->requirements_result = $report->toArray();
                $locked->requirements_checked_at = now();
            }
            $locked->transitioning = true;
            try {
                if ($next === ServiceRequestStatus::UnderReview && ! $locked->assigned_to) {
                    $locked->assigned_to = $actor->id;
                    $locked->assigned_by = $actor->id;
                    $locked->assigned_at = now();
                }
                $locked->status = $next->value;
                $locked->citizen_visible_reason = $visibleReason;
                $locked->active_request_key = $next->isTerminal() ? null : self::activeKey($locked->user_id, $locked->government_service_id, $locked->request_type ?? $service?->operation ?? 'unknown');
                $locked->review_version++;
                if ($next === ServiceRequestStatus::Completed) {
                    $locked->completed_at = now();
                    $locked->completed_by = $actor->id;
                }
                $locked->save();
                $decision = $actions->save($locked, $actor, $action, $current->value, $visibleReason, $context, $report?->toArray());
                if ($next === ServiceRequestStatus::RequiresCorrection) {
                    app(ReviewDeficiencies::class)->create($locked, $decision, $context['deficiencies']);
                }
            } finally {
                $locked->transitioning = false;
                $locked->correcting = false;
            }

            return $locked;
        });
    }

    protected function casts(): array
    {
        return ['service_fee' => 'decimal:2', 'service_rules_version' => 'integer', 'service_snapshot' => 'array', 'request_data' => 'array', 'original_request_data' => 'array', 'review_version' => 'integer', 'assigned_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'declared_at' => 'datetime', 'submitted_at' => 'datetime', 'requirements_result' => 'array', 'requirements_checked_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CitizenAttachment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(CitizenAppointment::class);
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(MedicalExamination::class);
    }

    public function charge()
    {
        return $this->hasOne(RequestCharge::class);
    }

    public function reviewDecisions(): HasMany
    {
        return $this->hasMany(ReviewDecision::class);
    }

    public function integrationAttempts(): HasMany
    {
        return $this->hasMany(IntegrationAttempt::class);
    }

    public function deficiencies(): HasMany
    {
        return $this->hasMany(RequestDeficiency::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function issuedDocument()
    {
        return $this->hasOne(CitizenDocument::class, 'citizen_service_request_id');
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
                $request->service_snapshot = $service->only(['code', 'name', 'document_type', 'operation', 'description', 'conditions', 'required_documents', 'required_fields', 'application_fields', 'verification_rules', 'workflow_rules', 'integration_rules', 'require_separate_approver', 'source_reference', 'fee', 'currency', 'estimated_duration', 'rules_version', 'minimum_age', 'renewal_statuses', 'renewal_window_days', 'requires_driving_clearance', 'terminal_request_statuses']);
            }
        });
        static::updating(function (self $request) {
            if ($request->isDirty('status') && ! $request->transitioning) {
                throw ValidationException::withMessages(['status' => 'لا يمكن تعديل حالة الطلب مباشرة. استخدم آلية الانتقال المخوّلة.']);
            }
            if ($request->getOriginal('submission_token') && $request->isDirty(['user_id', 'government_service_id', 'service_code', 'request_type', 'reference', 'submission_token', 'service_snapshot', 'service_name', 'service_fee', 'service_currency', 'service_rules_version', 'notes', 'declared_at', 'submitted_at'])) {
                throw ValidationException::withMessages(['request' => 'هوية الطلب وبيانات الخدمة التاريخية ثابتة بعد التقديم.']);
            }
            if ($request->getOriginal('submission_token') && ! $request->correcting && $request->isDirty(['request_data', 'original_request_data'])) {
                throw ValidationException::withMessages(['request' => 'تصحيح بيانات الطلب متاح فقط في مسار الاستكمال المصرح به.']);
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
