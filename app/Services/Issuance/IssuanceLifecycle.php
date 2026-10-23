<?php

namespace App\Services\Issuance;

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class IssuanceLifecycle
{
    public function assertTransition(CitizenServiceRequest $request, ServiceRequestStatus $next): void
    {
        $document = $request->issuedDocument()->with('delivery')->first();
        if (! $document || $document->user_id !== $request->user_id || $document->government_service_id !== $request->government_service_id || $document->status !== 'active' || $document->expires_at?->lt(today())) {
            throw ValidationException::withMessages(['document' => 'يلزم سجل وثيقة صادر وساري ومرتبط بهذا الطلب قبل التجهيز أو الإكمال.']);
        }
        $rules = app(IssuanceRules::class)->get($document->issuance_rules);
        if ($rules['electronic_copy'] !== 'none' && $document->copies()->latest('id')->first()?->contents() === null) {
            throw ValidationException::withMessages(['copy' => 'النسخة الإلكترونية المطلوبة مفقودة أو غير سليمة. أعد إنشاءها قبل المتابعة.']);
        }
        if ($next === ServiceRequestStatus::Completed && $rules['completion_stage'] === 'delivery' && ($document->delivery?->status !== 'delivered' || ! $document->delivery->delivered_by || ! $document->delivery->delivered_at || ! filled($document->delivery->proof_reference))) {
            throw ValidationException::withMessages(['delivery' => 'التسليم الموثق مطلوب قبل إكمال هذا الطلب.']);
        }
    }

    public function completeIfReady(CitizenServiceRequest $request, User $actor): void
    {
        $request = $request->fresh();
        $document = $request->issuedDocument;
        if ($request->status === 'processing' && (($document->issuance_rules['completion_stage'] ?? null) === 'issuance' || $document->delivery?->status === 'delivered')) {
            $request->transitionTo(ServiceRequestStatus::Completed, $actor);
            app(DocumentAudit::class)->record($document, $actor, 'completed', from: 'processing', to: 'completed');
        }
    }
}
