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
}
