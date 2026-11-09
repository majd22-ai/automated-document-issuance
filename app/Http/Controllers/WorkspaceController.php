<?php

namespace App\Http\Controllers;

use App\Models\CitizenServiceRequest;
use App\Services\Workspace\EmployeeWorkspace;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function employee(Request $request, EmployeeWorkspace $workspace)
    {
        $user = $request->user();
        $ids = $workspace->serviceIds($user);
        $assigned = CitizenServiceRequest::whereIn('government_service_id', $ids)->where('assigned_to', $user->id)->whereNotIn('status', ['completed', 'rejected'])->with('service')->latest('id')->limit(8)->get();
        $grants = $user->reviewPermissions()->with('service')->get();

        return response()->view('employee.dashboard', compact('assigned', 'grants') + ['modules' => $workspace->modules($user)])->header('Cache-Control', 'no-store, private');
    }

    public function citizen(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'citizen', 403);

        $applications = $user->serviceRequests();
        $metrics = [
            'إجمالي الطلبات' => (clone $applications)->count(),
            'تحتاج استكمالًا' => (clone $applications)->where('status', 'requires_correction')->count(),
            'وثائق مسجلة' => $user->documents()->count(),
            'مواعيد مفتوحة' => $user->appointments()->whereIn('status', ['booked', 'confirmed'])->count(),
        ];
        $recentApplications = $applications
            ->orderByRaw("CASE WHEN status = 'requires_correction' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(5)
            ->get();

        return response()->view('citizen.dashboard', compact('metrics', 'recentApplications'))->header('Cache-Control', 'no-store, private');
    }
}
