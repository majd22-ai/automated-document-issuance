<?php

namespace App\Observers;

use App\Models\IntegrationAttempt;
use App\Models\User;
use App\Services\Integrations\IntegrationManager;
use App\Services\Oversight\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class OperationalAuditObserver
{
    public function creating(Model $model): void
    {
        if ($model instanceof IntegrationAttempt && Schema::hasColumn('integration_attempts', 'origin')) {
            $adapter = config('integrations.providers.'.$model->provider.'.adapter', '');
            $model->setAttribute('origin', str_starts_with($adapter, 'Tests\\') ? 'simulation' : (app(IntegrationManager::class)->configured($model->provider) ? 'configured_provider' : 'unknown'));
        }
    }

    public function created(Model $model): void
    {
        $this->write($model, 'created');
    }

    public function updating(Model $model): void
    {
        if ($model instanceof User && $model->isDirty(['password', 'role', 'is_active', 'registration_status', 'national_id', 'account_activated_at', 'identity_verified_at']) && ! $model->isDirty('auth_version') && Schema::hasColumn('users', 'auth_version')) {
            $model->auth_version = (int) $model->auth_version + 1;
            $model->remember_token = null;
        }
    }

    public function updated(Model $model): void
    {
        $important = ['role', 'is_active', 'department_id', 'report_scope', 'can_export_reports', 'employee_number', 'job_title', 'name', 'email', 'username', 'password', 'registration_status', 'national_id', 'account_activated_at', 'identity_verified_at', 'description', 'status', 'assigned_to', 'result', 'finished_at', 'can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate', 'rules_version'];
        if ($model->wasChanged($important)) {
            $this->write($model, 'updated');
        }
    }

    private function write(Model $model, string $verb): void
    {
        $type = ['User' => 'user', 'Department' => 'department', 'CitizenServiceRequest' => 'request', 'ReviewDecision' => 'review', 'DocumentEvent' => 'document_event', 'CitizenDocument' => 'document', 'ManualPayment' => 'payment', 'RequestCharge' => 'charge', 'IntegrationAttempt' => 'integration', 'ServiceReviewPermission' => 'permission', 'GovernmentService' => 'service', 'MedicalExamination' => 'examination', 'CitizenAppointment' => 'appointment', 'CitizenAttachment' => 'attachment'][class_basename($model)];
        $actorId = $model->getAttribute('actor_id') ?? $model->getAttribute('recorded_by') ?? $model->getAttribute('issued_by');
        $actor = $verb === 'updated' && Auth::check() ? Auth::user() : ($actorId ? User::find($actorId) : Auth::user());
        $changes = [];
        foreach (['role', 'is_active', 'department_id', 'report_scope', 'can_export_reports', 'status', 'action', 'from_status', 'to_status', 'government_service_id', 'can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate'] as $field) {
            if ($verb === 'created' ? array_key_exists($field, $model->getAttributes()) : $model->wasChanged($field)) {
                $changes[$field] = ['before' => $verb === 'created' ? null : $model->getRawOriginal($field), 'after' => $model->getAttribute($field)];
            }
        }
        $changedFields = array_intersect(array_keys($verb === 'created' ? $model->getAttributes() : $model->getChanges()), ['name', 'username', 'registration_status', 'national_id', 'account_activated_at', 'identity_verified_at', 'email', 'employee_number', 'job_title', 'password', 'department_id', 'role', 'is_active', 'report_scope', 'can_export_reports', 'description', 'status', 'assigned_to', 'action', 'can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate', 'rules_version']);
        if ($changedFields) {
            $changes['changed_fields'] = array_values($changedFields);
        }
        app(AuditTrail::class)->record($type.'.'.$verb, $type, $model->getKey(), $actor, 'success', $changes);
    }
}
