<?php

namespace App\Services\Integrations;

use App\Contracts\IntegrationProvider;
use App\Enums\VerificationStatus as Status;
use App\Models\CitizenServiceRequest;
use App\Models\IntegrationAttempt;
use App\Models\User;
use App\Services\Operations\LockedRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IntegrationManager
{
    public function configured(?string $provider): bool
    {
        $config = config('integrations.providers.'.$provider, []);
        $class = $config['adapter'] ?? null;

        return ($config['enabled'] ?? false) === true && ($config['approved'] ?? false) === true && is_string($class) && is_subclass_of($class, IntegrationProvider::class)
            && (app()->environment('testing') || ! str_starts_with($class, 'Tests\\')) && filled($config['label'] ?? null);
    }

    public function start(User $actor, CitizenServiceRequest $request, string $kind, string $key, ?int $documentId = null): IntegrationAttempt
    {
        abort_unless(isset(IntegrationRules::KINDS[$kind]) && Str::isUuid($key), 422);
        [$attempt,$context,$rule] = app(LockedRequest::class)->run($actor, $request, 'verifyExternal', function ($actor, $owner, $service, $request) use ($kind, $key, $documentId) {
            abort_unless($owner->is_active && $owner->role === 'citizen' && $service->is_active && $service->rules_approved && filled($service->source_reference), 422, 'الحساب أو الخدمة غير متاحين لإجراء تحقق خارجي.');
            abort_if(in_array($request->status, ['completed', 'cancelled', 'rejected'], true), 422, 'الطلب مغلق ولا يقبل عملية تحقق جديدة.');
            $rule = app(IntegrationRules::class)->effective($request)[$kind] ?? null;
            if (! $rule) {
                throw ValidationException::withMessages(['verification' => 'لم تعتمد قاعدة لهذا التحقق على الخدمة.']);
            }
            $hash = hash('sha256', $request->id.':'.$key);
            if ($existing = IntegrationAttempt::where('idempotency_hash', $hash)->first()) {
                abort_unless($existing->kind === $kind && $existing->target_document_id === $documentId, 409);

                return [$existing, null, null];
            }
            abort_if($documentId && ($kind !== 'document' || ! $owner->documents()->where('document_type_code', $service->document_type)->whereKey($documentId)->exists()), 422, 'الوثيقة المختارة لا تخص هذا المواطن ونوع الخدمة.');
            $active = hash('sha256', $request->id.':'.$kind);
            if ($running = IntegrationAttempt::where('active_key', $active)->first()) {
                if ($running->started_at->gt(now()->subMinutes(2))) {
                    abort(409, 'توجد محاولة تحقق قيد التنفيذ؛ انتظر نتيجتها قبل بدء محاولة جديدة.');
                }
                $running->forceFill(['status' => 'unknown', 'error_code' => 'interrupted', 'active_key' => null, 'finished_at' => now()])->save();
            }
            $id = (string) Str::orderedUuid();
            $context = null;
            try {
                $context = VerificationContext::forRequest($request, $kind, $rule, $id, $documentId);
            } catch (ValidationException) {
            }
            $configured = $this->configured($rule['provider']);
            $status = ! $configured ? 'not_configured' : ($context ? 'in_progress' : 'unknown');
            $attempt = (new IntegrationAttempt)->forceFill(['id' => $id, 'citizen_service_request_id' => $request->id, 'user_id' => $owner->id, 'actor_id' => $actor->id, 'target_document_id' => $documentId, 'kind' => $kind, 'provider' => $rule['provider'], 'status' => $status, 'idempotency_hash' => $hash, 'active_key' => $status === 'in_progress' ? $active : null, 'input_fingerprint' => $context?->fingerprint ?? hash('sha256', 'missing'), 'started_at' => now(), 'finished_at' => $status === 'in_progress' ? null : now(), 'error_code' => $status === 'unknown' ? 'missing_data' : null]);
            $attempt->save();

            return [$attempt, $status === 'in_progress' ? $context : null, $rule];
        });
        if (! $context) {
            return $attempt;
        }
        $begin = microtime(true);
        $settings = config('integrations.providers.'.$attempt->provider);
        $code = null;
        try {
            $result = app($settings['adapter'])->verify($context, $settings);
            $decisive = in_array($result->status, [Status::Verified, Status::Rejected, Status::Mismatch], true);
            if (! in_array($result->status, [Status::Verified, Status::Rejected, Status::Mismatch, Status::Pending, Status::Unknown, Status::Unavailable, Status::Failed], true) || ($decisive && (! $result->bound || $result->attempts < 1 || $result->httpStatus < 200 || $result->httpStatus >= 300))) {
                $result = new VerificationResult(Status::Unknown, httpStatus: $result->httpStatus, attempts: $result->attempts);
            }
        } catch (ConnectionException) {
            $result = new VerificationResult(Status::Unavailable, attempts: 1);
            $code = 'connection_unavailable';
        } catch (\Throwable) {
            $result = new VerificationResult(Status::Failed, attempts: 1);
            $code = 'provider_error';
        }

        return app(LockedRequest::class)->run($actor, $request, 'verifyExternal', function ($actor, $owner, $service, $request) use ($attempt, $context, $rule, $result, $code, $begin) {
            $attempt = IntegrationAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status !== 'in_progress') {
                return $attempt;
            }
            $state = $result->status;
            if (! $owner->is_active || ! $service->is_active || ! $service->rules_approved || in_array($request->status, ['cancelled', 'rejected', 'completed'], true)) {
                $state = Status::Expired;
            }
            try {
                $current = app(IntegrationRules::class)->effective($request)[$attempt->kind] ?? [];
                $newContext = VerificationContext::forRequest($request, $attempt->kind, $current, $attempt->id, $attempt->target_document_id);
                if (! hash_equals($context->fingerprint, $newContext->fingerprint) || ! $this->configured($attempt->provider)) {
                    $state = Status::Expired;
                }
            } catch (ValidationException) {
                $state = Status::Expired;
            }
            $attempt->forceFill(['status' => $state->value, 'active_key' => null, 'finished_at' => now(), 'expires_at' => $state === Status::Verified ? now()->addMinutes($rule['max_age_minutes']) : null, 'attempts' => max(0, min(3, $result->attempts)), 'http_status' => $result->httpStatus, 'duration_ms' => (int) min(4294967295, max(0, (microtime(true) - $begin) * 1000)), 'error_code' => $code])->save();
            app(LockedRequest::class)->invalidate($request);

            return $attempt;
        });
    }

    public function status(CitizenServiceRequest $request, string $kind): array
    {
        $rule = app(IntegrationRules::class)->effective($request)[$kind] ?? null;
        $attempt = $request->integrationAttempts()->where('kind', $kind)->latest('created_at')->latest('id')->first();
        $state = Status::NotRequired;
        if ($rule) {
            $state = $this->configured($rule['provider']) ? Status::Unknown : Status::NotConfigured;
            if ($attempt && $attempt->provider === $rule['provider']) {
                $state = Status::tryFrom($attempt->status) ?? Status::Unknown;
                if ($state === Status::Verified) {
                    try {
                        $context = VerificationContext::forRequest($request, $kind, $rule, $attempt->id, $attempt->target_document_id);
                        if (! $attempt->expires_at || $attempt->expires_at->lte(now()) || ! hash_equals($attempt->input_fingerprint, $context->fingerprint)) {
                            $state = Status::Expired;
                        }
                    } catch (ValidationException) {
                        $state = Status::Expired;
                    }
                    if (! $this->configured($rule['provider'])) {
                        $state = Status::NotConfigured;
                    }
                }
                if ($state === Status::InProgress && $attempt->started_at->lte(now()->subMinutes(2))) {
                    $state = Status::Unknown;
                }
            }
        }

        return ['kind' => $kind, 'label' => IntegrationRules::KINDS[$kind], 'status' => $state->value, 'message' => $state->label(), 'provider' => $rule['provider'] ?? null, 'required' => (bool) ($rule['required'] ?? false), 'attempted_at' => $attempt?->started_at, 'attempt_id' => $attempt?->id, 'target_document_id' => $attempt?->target_document_id];
    }
}
