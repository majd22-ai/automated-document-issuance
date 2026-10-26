<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Oversight\AuditTrail;
use App\Services\Registration\CitizenIdentifiers;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class EmployeeManagementController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', 'in:employee,admin'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'is_active' => ['nullable', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $employees = User::with('department')->whereIn('role', ['employee', 'admin'])->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$s.'%')->orWhere('username', 'like', '%'.$s.'%')->orWhere('email', 'like', '%'.$s.'%')->orWhere('employee_number', 'like', '%'.$s.'%')));
        foreach (['role', 'department_id', 'is_active'] as $field) {
            if (isset($f[$field])) {
                $employees->where($field, $f[$field]);
            }
        }

        return response()->view('admin.employees.index', ['employees' => $employees->latest('id')->paginate(20)->withQueryString(), 'departments' => Department::orderBy('name')->get()])->header('Cache-Control', 'no-store, private');
    }

    public function create()
    {
        return $this->form(new User);
    }

    public function show(User $employee)
    {
        abort_unless(in_array($employee->role, ['employee', 'admin']), 404);

        return $this->form($employee);
    }

    private function form(User $employee)
    {
        return response()->view('admin.employees.form', compact('employee') + ['departments' => Department::orderBy('name')->get()])->header('Cache-Control', 'no-store, private');
    }

    public function store(SaveEmployeeRequest $request)
    {
        return $this->save($request, new User);
    }

    public function update(SaveEmployeeRequest $request, User $employee)
    {
        return $this->save($request, $employee);
    }

    private function save(SaveEmployeeRequest $request, User $employee)
    {
        app(CitizenIdentifiers::class)->serialized(fn () => DB::transaction(function () use ($request, $employee) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $actor->role === 'admin', 403);
            if ($employee->exists) {
                $employee = User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($employee->role, ['employee', 'admin']), 404);
            }
            $data = $request->validated();
            if (app(CitizenIdentifiers::class)->conflicts($data['username'], $employee->exists ? $employee->id : null)) {
                throw ValidationException::withMessages(['username' => 'اسم المستخدم يتعارض مع معرّف حساب قائم.']);
            }
            if ($employee->is($actor) && ($data['role'] !== 'admin' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'لا يمكنك تعطيل حسابك الإداري أو إزالة دوره.']);
            }
            if (! empty($data['department_id']) && ! Department::whereKey($data['department_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['department_id' => 'القسم غير نشط.']);
            }
            if ($data['report_scope'] === 'department' && empty($data['department_id'])) {
                throw ValidationException::withMessages(['department_id' => 'حدد قسمًا لمنحة التقارير.']);
            }
            if ($employee->exists && $employee->email !== $data['email']) {
                $employee->email_verified_at = null;
            }
            $employee->forceFill(Arr::only($data, ['name', 'username', 'email', 'employee_number', 'job_title', 'department_id', 'role', 'is_active', 'report_scope', 'can_export_reports', 'password']));
            if ($employee->exists && $employee->isDirty(['role', 'is_active'])) {
                $employee->auth_version++;
                $employee->remember_token = null;
            }
            $employee->save();
        }));

        return redirect()->route('admin.employees.index')->with('status', 'تم حفظ حساب الموظف.');
    }

    public function resetPassword(Request $request, User $employee)
    {
        abort_unless(in_array($employee->role, ['employee', 'admin']) && $employee->is_active, 422);
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if (! in_array($transport, ['smtp', 'ses', 'postmark', 'resend', 'mailgun', 'sendmail'], true)) {
            return back()->withErrors(['reset' => 'يلزم إعداد بريد حقيقي لإرسال رابط الاستعادة بأمان.']);
        }
        try {
            $status = Password::sendResetLink(['email' => $employee->email]);
        } catch (\Throwable) {
            return back()->withErrors(['reset' => 'تعذر إرسال رابط الاستعادة؛ راجع إعدادات البريد.']);
        }
        app(AuditTrail::class)->record('employee.password_reset_requested', 'user', $employee->id, $request->user(), $status === Password::RESET_LINK_SENT ? 'success' : 'failed');

        return back()->with('status', $status === Password::RESET_LINK_SENT ? 'أُرسل رابط الاستعادة إلى الموظف.' : 'تعذر إرسال الرابط أو أُرسل مؤخرًا.');
    }
}
