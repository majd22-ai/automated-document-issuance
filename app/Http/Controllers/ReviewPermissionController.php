<?php

namespace App\Http\Controllers;

use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Registration\ActivationRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReviewPermissionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->is_active, 403);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $employees = User::where('role', 'employee')->where('is_active', true)->orderBy('id')->get(['id', 'name']);
        $services = GovernmentService::orderBy('id')->get(['id', 'name']);
        $permissions = ServiceReviewPermission::with(['employee', 'service'])->latest('id')->paginate(20);

        return response()->view('admin.review-permissions', compact('employees', 'services', 'permissions'))->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $request, User $user)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->is_active, 403);
        $data = $request->validate(['government_service_id' => ['required', 'integer', 'exists:government_services,id'], 'can_review' => ['required', 'boolean'], 'can_approve' => ['required', 'boolean'], 'can_assign' => ['required', 'boolean'], 'can_issue' => ['sometimes', 'boolean'], 'can_deliver' => ['sometimes', 'boolean'], 'can_verify' => ['sometimes', 'boolean'], 'can_activate' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $user, $data) {
            $service = GovernmentService::whereKey($data['government_service_id'])->lockForUpdate()->firstOrFail();
            if (($data['can_activate'] ?? false) && $service->code !== 'identity-first-issue') {
                throw ValidationException::withMessages(['can_activate' => 'صلاحية تفعيل المواطن تمنح ضمن خدمة إصدار البطاقة لأول مرة فقط.']);
            }
            abort_unless($request->user()->fresh()?->role === 'admin' && $request->user()->fresh()?->is_active, 403);
            $target = $user->fresh();
            abort_unless($target->role === 'employee' && $target->is_active, 422, 'الصلاحية لموظف نشط فقط.');
            $permission = ServiceReviewPermission::where('user_id', $target->id)->where('government_service_id', $data['government_service_id'])->first() ?? new ServiceReviewPermission;
            $hadActivation = (bool) $permission->can_activate;
            $permission->forceFill(array_merge($data, ['user_id' => $target->id, 'granted_by' => $request->user()->id]))->save();
            if (! $hadActivation && $permission->can_activate) {
                app(ActivationRequests::class)->permissionGranted($target);
            }
            DB::afterCommit(function () use ($permission, $request) {
                try {
                    Log::info('review_permissions_changed', ['permission_id' => $permission->id, 'actor_id' => $request->user()->id]);
                } catch (\Throwable) {
                }
            });
        });

        return redirect()->route('admin.review-permissions.index')->with('status', 'تم تحديث صلاحيات الخدمة للموظف. لا تمنح الصلاحيات تلقائيًا لبقية الموظفين.');
    }
}
