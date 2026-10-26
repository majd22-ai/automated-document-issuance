<?php

namespace App\Services;

use App\Http\Requests\PreviewServiceRequest;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Attachments\AttachmentSubmission;
use App\Services\Notifications\CitizenUpdates;
use App\Services\Operations\RequestCharges;
use App\Services\Requirements\EligibilityResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceRequestSubmission
{
    public function __construct(private ServiceEligibility $eligibility, private AttachmentSubmission $attachments) {}

    public static function citizenFingerprint(User $citizen): string
    {
        $data = $citizen->only(['name', 'username', 'email', 'phone']);
        if ($citizen->registration_status !== null) {
            $data += $citizen->only(['national_id', 'registration_status', 'identity_path', 'account_activated_at', 'identity_verified_at']);
        }

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function preview(User $citizen, GovernmentService $service, array $payload): array
    {
        Gate::forUser($citizen)->authorize('create', CitizenServiceRequest::class);
        $this->assertEligible($citizen, $service);
        $payload = Validator::make($payload, PreviewServiceRequest::payloadRules($service))->validate();

        return [
            'user_id' => $citizen->id, 'service_id' => $service->id,
            'service_fingerprint' => $service->submissionFingerprint(),
            'citizen_fingerprint' => self::citizenFingerprint($citizen),
            'attachments_fingerprint' => $this->attachments->fingerprint($citizen, $service),
            'payload' => $payload, 'expires_at' => now()->addMinutes(30)->timestamp,
        ];
    }

    public function assertEligible(User $citizen, GovernmentService $service): EligibilityResult
    {
        $report = $this->eligibility->evaluate($citizen, $service);
        $this->eligibility->audit($report, $citizen, $service);
        if (! $report->eligible()) {
            throw ValidationException::withMessages(['service' => $report->reasons()]);
        }

        return $report;
    }

    public function assertCurrentPreview(User $citizen, GovernmentService $service, ?array $preview): void
    {
        abort_unless($preview && $preview['user_id'] === $citizen->id && $preview['service_id'] === $service->id, 404);
        abort_if($preview['expires_at'] < now()->timestamp, 410, 'انتهت صلاحية الملخص. راجع الطلب من جديد.');
        if (! hash_equals($preview['service_fingerprint'], $service->submissionFingerprint()) || ! hash_equals($preview['citizen_fingerprint'], self::citizenFingerprint($citizen))) {
            throw ValidationException::withMessages(['preview_token' => 'تغيرت بيانات المواطن أو الخدمة منذ المراجعة. أنشئ ملخصًا جديدًا قبل الإرسال.']);
        }
        if (! isset($preview['attachments_fingerprint']) || ! hash_equals($preview['attachments_fingerprint'], $this->attachments->fingerprint($citizen, $service))) {
            throw ValidationException::withMessages(['preview_token' => 'تغيرت المرفقات منذ المعاينة. راجع الملخص الجديد قبل التأكيد.']);
        }
    }

    /** Only called after server-side declaration validation; no browser payload is stored here. */
    public function submit(User $actor, GovernmentService $service, string $token, ?array $preview): CitizenServiceRequest
    {
        Gate::forUser($actor)->authorize('create', CitizenServiceRequest::class);
        try {
            return DB::transaction(function () use ($actor, $service, $token, $preview) {
                // Fixed lock order serializes all submissions for one citizen, including legacy aliases.
                $citizen = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($citizen)->authorize('create', CitizenServiceRequest::class);
                $service = GovernmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
                $existing = $citizen->serviceRequests()->where('submission_token', $token)->first();
                if ($existing) {
                    abort_unless($existing->government_service_id === $service->id, 404);

                    return $existing;
                }
                $this->assertCurrentPreview($citizen, $service, $preview);
                $report = $this->assertEligible($citizen, $service);
                $payload = Validator::make($preview['payload'], PreviewServiceRequest::payloadRules($service))->validate();
                $request = $citizen->serviceRequests()->make(['reference' => 'REQ-'.Str::ulid(), 'service_code' => $service->code, 'status' => 'submitted']);
                $request->forceFill([
                    'request_type' => $service->operation, 'request_data' => $payload['data'] ?? [],
                    'notes' => $payload['notes'] ?? null, 'declared_at' => now(), 'submitted_at' => now(),
                    'submission_token' => $token, 'active_request_key' => CitizenServiceRequest::activeKey($citizen->id, $service->id, $service->operation),
                    'requirements_result' => $report->toArray(), 'requirements_checked_at' => now(),
                ])->save();

                $this->attachments->bind($citizen, $service, $request);
                app(RequestCharges::class)->ensure($request);
                app(CitizenUpdates::class)->submitted($request);

                return $request;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Unique indexes are a second line of defense, including unsupported lock drivers.
            $existing = $actor->serviceRequests()->where('submission_token', $token)->where('government_service_id', $service->id)->first();
            if ($existing) {
                return $existing;
            }
            throw ValidationException::withMessages(['service' => 'تعذر إنشاء طلب جديد؛ قد يوجد طلب قائم أو إرسال متزامن. راجع طلباتك قبل المحاولة مجددًا.']);
        }
    }
}
