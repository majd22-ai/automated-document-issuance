<?php

namespace App\Policies;

use App\Models\CitizenDocument;
use App\Models\User;

class CitizenDocumentPolicy
{
    public function view(User $user, CitizenDocument $document): bool
    {
        return $user->is_active && (($user->role === 'citizen' && $document->user_id === $user->id)
            || $user->canReviewService($document->government_service_id, 'can_issue') || $user->canReviewService($document->government_service_id, 'can_deliver'));
    }

    public function download(User $user, CitizenDocument $document): bool
    {
        return $this->view($user, $document) && ($user->role !== 'citizen' || ($document->issuance_rules['citizen_download'] ?? false));
    }

    public function regenerate(User $user, CitizenDocument $document): bool
    {
        return $this->view($user, $document) && $user->canReviewService($document->government_service_id, 'can_issue');
    }

    public function chooseDelivery(User $user, CitizenDocument $document): bool
    {
        return $this->view($user, $document) && ($user->role === 'citizen' || $user->canReviewService($document->government_service_id, 'can_deliver'));
    }

    public function updateDelivery(User $user, CitizenDocument $document): bool
    {
        return $this->view($user, $document) && $user->canReviewService($document->government_service_id,'can_deliver');
    }
}
