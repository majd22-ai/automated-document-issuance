<?php

namespace App\Services\Notifications;

use App\Models\CitizenAppointment;
use App\Models\CitizenAttachment;
use App\Models\CitizenServiceRequest;
use App\Models\DocumentEvent;
use App\Models\IntegrationAttempt;
use App\Models\ManualPayment;
use App\Models\MedicalExamination;
use App\Models\ReviewDecision;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Notifications\CitizenActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Internal inbox events are written on the operation's connection and transaction. */
class OperationalNotifications
{
    private ?User $actor = null;

    public function asActor(User $actor, callable $work): mixed
    {
        $previous = $this->actor;
        $this->actor = $actor;
        try {
            return $work();
        } finally {
            $this->actor = $previous;
        }
    }

    public function employeeResetRequested(User $employee, User $actor): void
    {
        if ($actor->role === 'admin' && $actor->is_active && $employee->role === 'employee' && $employee->is_active && $actor->id !== $employee->id) {
            $this->send($employee, 'employee-reset-request:'.Str::uuid(), 'employee_account', 'طلب استعادة كلمة المرور', 'تم إرسال تعليمات استعادة كلمة المرور لحسابك عبر القناة المعتمدة.', target: ['target_user_id' => $employee->id]);
        }
    }

    public function record(Model $model, string $verb): void
    {
        $actor = $this->actor ?? Auth::user();
        if (! $actor) {
            $actorId = $model->getAttribute('actor_id') ?? $model->getAttribute('recorded_by') ?? $model->getAttribute('reviewed_by') ?? $model->getAttribute('granted_by');
            $actor = $actorId ? User::find($actorId) : null;
            if (! $actor && $model instanceof CitizenAttachment && ($verb !== 'updated' || $model->wasChanged('storage_path'))) {
                $actor = $model->citizen;
            }
        }

        if ($model instanceof User) {
            if ($actor?->role !== 'admin' || $actor->id === $model->id || ($model->role !== 'employee' && $model->getOriginal('role') !== 'employee')) {
                return;
            }
            if ($verb === 'created' || ($verb === 'updated' && $model->wasChanged(['name', 'username', 'email', 'password', 'role', 'is_active', 'department_id', 'employee_number', 'job_title', 'report_scope', 'can_export_reports', 'must_change_password']))) {
                // Account-state changes are retained for the target even while its login is disabled.
                $this->send($model, $this->key($model, $verb), 'employee_account', 'تحديث حساب الموظف', 'تم تحديث بيانات حسابك أو صلاحيات الوصول. راجع حسابك والتعليمات المعتمدة.', target: ['target_user_id' => $model->id], allowInactive: true);
            }

            return;
        }

        if ($model instanceof ServiceReviewPermission) {
            if ($actor?->role !== 'admin' || ! in_array($verb, ['created', 'deleted'], true) && ! $model->wasChanged(['can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate'])) {
                return;
            }
            // The existing activation grant event remains the sole notification for that grant.
            if ($verb !== 'deleted' && $model->can_activate && ($verb === 'created' || $model->wasChanged('can_activate'))) {
                return;
            }
            $employee = $model->employee;
            if ($employee?->role === 'employee') {
                $this->send($employee, $this->key($model, $verb), 'employee_permission', 'تحديث صلاحيات الخدمة', 'تم تحديث صلاحياتك على إحدى الخدمات. راجع الخدمات المتاحة في حسابك.', target: ['target_user_id' => $employee->id]);
            }

            return;
        }

        if ($model instanceof CitizenServiceRequest) {
            if (($verb === 'created' || ($verb === 'updated' && $model->wasChanged('assigned_to'))) && $model->assigned_to) {
                $employee = User::find($model->assigned_to);
                if ($employee?->role === 'employee' && $employee->can('track', $model)) {
                    $this->send($employee, $this->key($model, $verb), 'request_assignment', 'إسناد طلب إليك', 'تم إسناد طلب إلى قائمة عملك. راجع تفاصيل الطلب.', $model);
                }
            }

            return;
        }

        $kind = match (true) {
            $model instanceof ReviewDecision, $model instanceof DocumentEvent => 'request_activity',
            $model instanceof CitizenAttachment => 'attachment_update',
            $model instanceof CitizenAppointment => 'appointment_update',
            $model instanceof ManualPayment => 'payment_update',
            $model instanceof MedicalExamination => 'examination_update',
            $model instanceof IntegrationAttempt => 'integration_update',
            default => null,
        };
        if (! $kind || ($verb === 'updated' && ! $model->wasChanged(['status', 'review_status', 'storage_path', 'version_token', 'result', 'finished_at', 'appointment_slot_id']))) {
            return;
        }
        if ($model instanceof IntegrationAttempt && ! $model->finished_at) {
            return;
        }
        $request = match (true) {
            $model instanceof ManualPayment => $model->charge?->application,
            $model instanceof DocumentEvent => $model->document?->application,
            default => $model->getAttribute('citizen_service_request_id') ? CitizenServiceRequest::find($model->citizen_service_request_id) : null,
        };
        if (! $request && $model instanceof CitizenAttachment && in_array($actor?->role, ['employee', 'admin'], true) && $model->citizen?->role === 'citizen') {
            $this->send($model->citizen, $this->key($model, $verb), $kind, 'تحديث على مرفقك', 'تم تسجيل تحديث على مراجعة مرفقك. راجع المرفقات والإجراء المطلوب.', target: ['attachment_id' => $model->id, 'government_service_id' => $model->government_service_id]);
        }
        if (! $request) {
            return;
        }
        if ($actor?->role === 'citizen' && (int) $actor->id === (int) $request->user_id) {
            $employee = User::find($request->assigned_to);
            if ($employee?->role === 'employee' && $employee->can('track', $request)) {
                $this->send($employee, $this->key($model, $verb), $kind, 'تحديث على طلب مسند إليك', 'سجل المواطن إجراء جديداً على الطلب. راجع تفاصيل الطلب لمتابعته.', $request, document: $model instanceof DocumentEvent ? $model->citizen_document_id : null);
            }
        } elseif (in_array($actor?->role, ['employee', 'admin'], true)
            && (! ($model instanceof ReviewDecision) || in_array($model->action, ['assign', 'resolve'], true))
            && (! ($model instanceof DocumentEvent) || ! in_array($model->action, ['issued', 'delivery_selected', 'delivery_status'], true))) {
            $citizen = $request->citizen;
            if ($citizen?->role === 'citizen') {
                $this->send($citizen, $this->key($model, $verb), $kind, 'تحديث على طلبك', 'تم تسجيل تحديث على أحد إجراءات طلبك. راجع تفاصيل الطلب والإجراء المطلوب.', $request, document: $model instanceof DocumentEvent ? $model->citizen_document_id : null);
            }
        }
    }

    private function key(Model $model, string $verb): string
    {
        // Created/removed records have stable event identity. Updates only arrive after
        // real dirty saves; a fresh identity preserves later legitimate repeated changes.
        return 'operation:'.class_basename($model).':'.$model->getKey().':'.$verb.($verb === 'updated' ? ':'.Str::uuid() : '');
    }

    private function send(User $recipient, string $key, string $kind, string $title, string $body, ?CitizenServiceRequest $request = null, array $target = [], bool $allowInactive = false, ?int $document = null): void
    {
        if (! $allowInactive && ! $recipient->is_active) {
            return;
        }
        $payload = ['kind' => $kind, 'title' => $title, 'body' => $body, 'occurred_at' => now()->toIso8601String()] + $target;
        if ($request) {
            $payload['request_reference'] = $request->reference;
        }
        $recipient->notifyNow(new CitizenActivity($key, $payload, $request?->id, $document));
    }
}
