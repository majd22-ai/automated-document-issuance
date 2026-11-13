<?php

namespace App\Services\DemoCivilRegistry;

use App\Models\DemoCivilRegistryAudit;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        Request $request,
        string $action,
        string $outcome = 'success',
        ?int $personId = null,
        ?int $recordId = null,
        ?int $documentId = null,
        array $metadata = [],
    ): void {
        $requestId = $request->attributes->get('demo_registry_request_id');
        $user = $request->user();
        $key = (string) config('app.key');

        (new DemoCivilRegistryAudit)->forceFill([
            'user_id' => $user?->id,
            'actor_role' => $user?->role ?? 'guest',
            'action' => mb_substr($action, 0, 80),
            'person_id' => $personId,
            'record_id' => $recordId,
            'document_id' => $documentId,
            'citizen_service_request_id' => $requestId,
            'outcome' => $outcome,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), $key) : null,
            'user_agent_hash' => $request->userAgent() ? hash_hmac('sha256', $request->userAgent(), $key) : null,
            'metadata' => $metadata,
        ])->save();
    }
}