<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return response()->view('admin.departments', ['departments' => Department::withCount('employees')->orderBy('name')->paginate(20)])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        return $this->save($request, new Department);
    }

    public function update(Request $request, Department $department)
    {
        return $this->save($request, $department);
    }

    private function save(Request $request, Department $department)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150', Rule::unique('departments')->ignore($department->id)], 'description' => ['nullable', 'string', 'max:500'], 'is_active' => ['required', 'boolean']]);
        DB::transaction(fn () => $department->forceFill($data)->save());

        return back()->with('status', 'تم حفظ القسم؛ تعطيله لا يحذف الموظفين أو التاريخ.');
    }
}
