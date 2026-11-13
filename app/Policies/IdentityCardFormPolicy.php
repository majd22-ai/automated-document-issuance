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
        if ($form->journey_version === 1 && $form->submitted_at && ! $form->correctionIsOpen()) { return false; }
        return IdentityAttestations::enabled() && $actor->is_active && $actor->role === 'citizen'
            && $actor->id === $form->user_id
            && ((! $form->submitted_at && $form->current_step === 6 && $form->status === 'awaiting_data')
                || ($form->correctionIsOpen() && $form->current_step === 6)
                || ($form->submitted_at !== null && ! $form->correctionIsOpen()));
    }

    public function simulate(User $actor, IdentityCardForm $form): bool
    {
        if (data_get($form->attestation?->recipient_scope, 'employee_id')) {
            return false;
        }

        return IdentityAttestations::enabled() && $actor->is_active
            && $actor->id !== $form->user_id
            && ($actor->role === 'admin' || ($actor->role === 'employee' && $actor->canReviewService($form->government_service_id, 'can_review')));
    }

    public function recordFingerprint(User $actor, IdentityCardForm $form): bool
    {
        return FingerprintService::enabled() && IdentityAttestations::enabled() && $actor->is_active && $actor->id !== $form->user_id
            && ($actor->role === 'admin' || ($actor->role === 'employee' && $actor->canReviewService($form->government_service_id, 'can_review')));
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
