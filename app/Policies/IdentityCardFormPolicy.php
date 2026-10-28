<?php

namespace App\Policies;

use App\Models\IdentityCardForm;
use App\Models\User;
use App\Services\FingerprintService;
use App\Services\IdentityAttestations;

class IdentityCardFormPolicy
{
    public function chooseAttestation(User $actor, IdentityCardForm $form): bool
    {
        return IdentityAttestations::enabled() && $actor->is_active && $actor->role === 'citizen'
            && $actor->id === $form->user_id && $form->submitted_at !== null && ! $form->correctionIsOpen();
    }

    public function simulate(User $actor, IdentityCardForm $form): bool
    {
        return IdentityAttestations::enabled() && $actor->is_active
            && $actor->id !== $form->user_id
            && ($actor->role === 'admin' || ($actor->role === 'employee' && $actor->canReviewService($form->government_service_id, 'can_review')));
    }

    public function recordFingerprint(User $actor, IdentityCardForm $form): bool
    {
        return FingerprintService::enabled() && $this->simulate($actor, $form);
    }

    public function administer(User $actor, IdentityCardForm $form): bool
    {
        return FingerprintService::enabled() && $actor->is_active && $actor->id !== $form->user_id
            && ($actor->role === 'admin' || ($actor->role === 'employee' && $actor->canReviewService($form->government_service_id, 'can_approve')));
    }

    public function viewPrintable(User $actor, IdentityCardForm $form): bool
    {
        return $actor->is_active && $form->submitted_at && (($actor->role === 'citizen' && $actor->id === $form->user_id)
            || $actor->role === 'admin' || ($actor->role === 'employee' && ($actor->canReviewService($form->government_service_id, 'can_review') || $actor->canReviewService($form->government_service_id, 'can_approve'))));
    }
}
