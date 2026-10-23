<?php

namespace App\Services\Review;

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\ReviewDecision;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewActions
{
    public function action(ServiceRequestStatus $next): string
    {
        return match ($next) {
            ServiceRequestStatus::UnderReview => 'start',ServiceRequestStatus::Accepted => 'accept',ServiceRequestStatus::RequiresCorrection => 'return',ServiceRequestStatus::Rejected => 'reject',ServiceRequestStatus::Approved => 'approve',ServiceRequestStatus::Submitted => 'resubmit', default => $next->value
        };
    }

    public function commandHash(string $action, ?string $reason, array $context): string
    {
        return hash('sha256', json_encode([$action, $reason, $context['internal_notes'] ?? null, $context['deficiencies'] ?? [], $context['data'] ?? [], $context['responses'] ?? [], $context['assigned_to'] ?? null, $context['resolution_note'] ?? null], JSON_THROW_ON_ERROR));
    }

    public function replay(CitizenServiceRequest $request, User $actor, string $action, ?string $reason, array $context): ?ReviewDecision
    {
        if (! isset($context['decision_token'])) {
            return null;
        }
        $decision = ReviewDecision::where('decision_token', $context['decision_token'])->first();
        if (! $decision) {
            return null;
        }
        if ($decision->citizen_service_request_id !== $request->id || $decision->actor_id !== $actor->id || ! hash_equals($decision->command_hash, $this->commandHash($action, $reason, $context))) {
            throw ValidationException::withMessages(['decision_token' => 'رمز القرار مستخدم لعملية مختلفة؛ لا يمكن إعادة استخدامه.']);
        }

        return $decision;
    }

    public function assertCurrent(CitizenServiceRequest $request, array $context): void
    {
        if ((isset($context['expected_version']) && (int) $context['expected_version'] !== $request->review_version)
            || (isset($context['expected_rules_version']) && (int) $context['expected_rules_version'] !== $request->service?->rules_version)
            || (isset($context['expected_fingerprint']) && ! hash_equals(app(ReviewSnapshot::class)->fingerprint($request), $context['expected_fingerprint']))) {
            throw ValidationException::withMessages(['review' => 'تغير الطلب أو إسناده أو قواعده أو أدلته منذ فتح الصفحة. حدّثها وراجع النتائج الحالية.']);
        }
    }

    public function save(CitizenServiceRequest $request, User $actor, string $action, ?string $from, ?string $reason, array $context, ?array $report = null, array $details = []): ReviewDecision
    {
        $decision = (new ReviewDecision)->forceFill(['citizen_service_request_id' => $request->id, 'actor_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $request->status, 'decision_token' => $context['decision_token'] ?? (string) Str::uuid(), 'command_hash' => $this->commandHash($action, $reason, $context), 'review_version' => $request->review_version, 'public_reason' => $reason, 'internal_notes' => $context['internal_notes'] ?? null, 'details' => $details, 'requirements_result' => $report, 'decided_at' => now()]);
        $decision->save();
        app(\App\Services\Notifications\CitizenUpdates::class)->review($decision);

        return $decision;
    }
}
