<?php

namespace App\Observers;

use App\Models\CitizenServiceRequest;
use App\Services\Oversight\AuditTrail;
use Illuminate\Support\Facades\Auth;

class IdentityAttestationObserver
{
    public function updated(CitizenServiceRequest $application): void
    {
        if (! $application->wasChanged('status') || ! in_array($application->status, ['cancelled', 'rejected'], true)) {
            return;
        }
        $form = $application->identityCardForm()->lockForUpdate()->first();
        $attestation = $form?->attestation()->lockForUpdate()->first();
        if ($attestation?->status === 'pending') {
            $attestation->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            app(AuditTrail::class)->record('identity_attestation_cancelled', get_class($attestation), $attestation->id, Auth::user(), changes: ['status' => 'cancelled']);
        }
        if ($form) {
            $form->forceFill(['status' => $application->status === 'rejected' ? 'rejected' : 'cancelled', 'correction_opened_at' => null, 'current_step' => 7, 'version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_workflow_closed', get_class($form), $form->id, Auth::user(), changes: ['status' => $form->status]);
        }
    }
}
