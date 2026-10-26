<?php

namespace App\Policies;

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\User;

class CitizenServiceRequestPolicy
{
    public function verifyExternal(User $user, CitizenServiceRequest $application): bool
    {
        return $user->canReviewService($application->government_service_id, 'can_verify');
    }

    public function viewVerification(User $user, CitizenServiceRequest $application): bool
    {
        return ($user->is_active && $user->role === 'citizen' && $application->user_id === $user->id)
            || $this->verifyExternal($user, $application);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === 'citizen' && (! $user->needsCitizenActivation());
    }

    public function track(User $user, CitizenServiceRequest $application): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if ($user->role === 'citizen') {
            return $application->user_id === $user->id;
        }
        foreach (['can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver'] as $ability) {
            if ($user->canReviewService($application->government_service_id, $ability)) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, CitizenServiceRequest $application): bool
    {
        return $user->is_active && (($user->role === 'citizen' && $application->user_id === $user->id)
            || ($user->role === 'employee' && $user->can('viewCitizenData', $application->citizen)));
    }

    public function viewReview(User $user, CitizenServiceRequest $application): bool
    {
        return $this->view($user, $application) && ($user->canReviewService($application->government_service_id, 'can_review') || $user->canReviewService($application->government_service_id, 'can_approve') || $user->canReviewService($application->government_service_id, 'can_assign'));
    }

    public function assignReview(User $user, CitizenServiceRequest $application): bool
    {
        return $this->viewReview($user, $application) && $user->canReviewService($application->government_service_id, 'can_assign');
    }

    public function resolveReview(User $user, CitizenServiceRequest $application): bool
    {
        return $this->viewReview($user, $application) && $user->canReviewService($application->government_service_id, 'can_review') && $application->assigned_to === $user->id && $application->status === 'under_review';
    }

    public function transition(User $user, CitizenServiceRequest $application, ?ServiceRequestStatus $next = null): bool
    {
        if (in_array($next, [ServiceRequestStatus::Processing, ServiceRequestStatus::Completed], true)) {
            return $this->issueDocument($user, $application) || ($next === ServiceRequestStatus::Completed && $this->deliverDocument($user, $application));
        }
        if ($next === ServiceRequestStatus::Submitted) {
            return $user->is_active && $user->role === 'citizen' && $application->user_id === $user->id;
        }
        if (! $this->viewReview($user, $application)) {
            return false;
        }
        if ($next === ServiceRequestStatus::Approved) {
            $separate = ($application->service_snapshot['require_separate_approver'] ?? false) || $application->service?->require_separate_approver;
            $accept = $application->reviewDecisions()->where('action', 'accept')->latest('id')->first();

            return $user->canReviewService($application->government_service_id, 'can_approve') && (! $separate || ! $accept || $accept->actor_id !== $user->id);
        }

        return in_array($next, [ServiceRequestStatus::UnderReview, ServiceRequestStatus::RequiresCorrection, ServiceRequestStatus::Rejected, ServiceRequestStatus::Accepted], true)
            && $user->canReviewService($application->government_service_id, 'can_review')
            && ($application->assigned_to === $user->id || ($next === ServiceRequestStatus::UnderReview && ! $application->assigned_to));
    }

    public function operate(User $user, CitizenServiceRequest $application): bool
    {
        return $this->view($user, $application) && in_array($application->status, ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved'], true);
    }

    public function issueDocument(User $user, CitizenServiceRequest $application): bool
    {
        return $user->canReviewService($application->government_service_id, 'can_issue');
    }

    public function deliverDocument(User $user, CitizenServiceRequest $application): bool
    {
        return $user->canReviewService($application->government_service_id, 'can_deliver');
    }

    public function recordOperations(User $user, CitizenServiceRequest $application): bool
    {
        return $user->role === 'employee' && $this->operate($user, $application);
    }
}
