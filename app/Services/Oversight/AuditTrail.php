<?php

namespace App\Services\Oversight;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditTrail
{
    public function record(string $event, string $type, int|string|null $id, ?User $actor = null, string $result = 'success', array $changes = []): ?AuditEvent
    {
        // Compatibility while installing migrations; never swallow a persistence failure.
        if (! Schema::hasTable('audit_events')) {
            return null;
        }
        $safe = [];
        foreach (['changed_fields', 'role', 'is_active', 'department_id', 'report_scope', 'can_export_reports', 'status', 'action', 'from_status', 'to_status', 'government_service_id', 'can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate', 'report', 'from', 'to'] as $key) {
            if (array_key_exists($key, $changes)) {
                $safe[$key] = $changes[$key];
            }
        }
        $entry = (new AuditEvent)->forceFill(['trace_id' => (string) Str::uuid(), 'event' => $event, 'subject_type' => $type, 'subject_id' => $id === null ? null : (string) $id, 'actor_id' => $actor?->id, 'department_id' => $actor?->department_id, 'result' => $result, 'changes' => $safe, 'occurred_at' => now()]);
        $entry->save();

        return $entry;
    }
}
