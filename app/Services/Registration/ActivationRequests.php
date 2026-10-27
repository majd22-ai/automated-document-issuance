<?php

namespace App\Services\Registration;

use App\Models\CitizenActivationRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Oversight\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivationRequests
{
    public function submit(User $citizen): CitizenActivationRequest
    {
        return DB::transaction(function () use ($citizen) {
            $citizen = User::whereKey($citizen->id)->lockForUpdate()->firstOrFail();
            abort_unless($citizen->role === 'citizen' && $citizen->is_active, 403);
            if ($citizen->hasActivatedIdentity() || ! $citizen->nationalIdentity()) {
                throw ValidationException::withMessages(['activation' => 'طلب التفعيل يتطلب رقمًا وطنيًا مسجلًا وحسابًا لم يكتمل توثيقه. الحساب الأولي يتبع إجراءات إصدار البطاقة أولًا.']);
            }
            if ($existing = CitizenActivationRequest::where('user_id', $citizen->id)->first()) {
                return $existing;
            }
            $entry = (new CitizenActivationRequest)->forceFill(['user_id' => $citizen->id, 'status' => 'pending', 'submitted_at' => now()]);
            $entry->save();
            $serviceId = GovernmentService::where('code', 'identity-first-issue')->value('id');
            User::where('role', 'employee')->where('is_active', true)
                ->whereHas('reviewPermissions', fn ($q) => $q->where('government_service_id', $serviceId)->where('can_activate', true))
                ->chunkById(100, function ($employees) use ($entry) {
                    foreach ($employees as $employee) {
                        $employee->notifyNow(new CitizenActivity('activation-request:'.$entry->id, [
                            'kind' => 'activation_request', 'title' => 'طلب تفعيل حساب مواطن جديد',
                            'body' => 'وصل طلب تفعيل حساب إلى قائمة المراجعة. افتح التفاصيل وراجع الإجراءات المعتمدة.',
                            'citizen_id' => $entry->user_id,
                        ]));
                    }
                });
            app(AuditTrail::class)->record('citizen_account.activation_requested', 'activation_request', $entry->id, $citizen, changes: ['status' => 'pending']);

            return $entry;
        }, 3);
    }

    public function permissionGranted(User $employee): void
    {
        $employee->notifyNow(new CitizenActivity('activation-grant:'.Str::uuid(), [
            'kind' => 'activation_permission', 'title' => 'تم منحك صلاحية تفعيل حسابات المواطنين',
            'body' => 'يمكنك مراجعة طلبات التفعيل من قسم تفعيل حسابات المواطنين. راجع القائمة لعرض الطلبات السابقة أيضًا.',
        ]));
    }
}
