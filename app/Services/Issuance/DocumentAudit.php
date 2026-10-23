<?php

namespace App\Services\Issuance;

use App\Models\CitizenDocument;
use App\Models\DocumentEvent;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentAudit
{
    public function hash(string $action, array $data): string
    {
        return hash('sha256', json_encode([$action, array_intersect_key($data, array_flip(['previous_document_id', 'method', 'center', 'address', 'status', 'reason', 'proof_reference']))], JSON_THROW_ON_ERROR));
    }

    public function replay(CitizenDocument $document, User $actor, string $action, array $data): bool
    {
        $event = DocumentEvent::where('operation_token', $data['operation_token'])->first();
        if (! $event) {
            return false;
        }
        if ($event->citizen_document_id !== $document->id || $event->actor_id !== $actor->id || ! hash_equals($event->command_hash, $this->hash($action, $data))) {
            throw ValidationException::withMessages(['operation_token' => 'رمز العملية مستخدم لإجراء مختلف.']);
        }

        return true;
    }

    public function current(CitizenDocument $document, array $data): void
    {
        if ((int) $data['expected_version'] !== $document->workflow_version) {
            throw ValidationException::withMessages(['document' => 'تغيرت الوثيقة أو عملية التسليم منذ فتح الصفحة. حدّثها أولًا.']);
        }
    }

    public function record(CitizenDocument $document, User $actor, string $action, array $data = [], ?string $from = null, ?string $to = null, array $details = []): DocumentEvent
    {
        $event = (new DocumentEvent)->forceFill(['citizen_document_id' => $document->id, 'actor_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'operation_token' => $data['operation_token'] ?? (string) Str::uuid(), 'command_hash' => $this->hash($action, $data), 'reason' => $data['reason'] ?? null, 'details' => $details, 'occurred_at' => now()]);
        $event->save();
        app(\App\Services\Notifications\CitizenUpdates::class)->document($event);

        return $event;
    }
}
