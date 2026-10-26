<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()->is_active;
    }

    public function rules(): array
    {
        $id = $this->route('employee')?->id;

        return ['name' => ['required', 'string', 'max:100'], 'username' => ['required', 'string', 'max:100', Rule::unique('users')->ignore($id)], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($id)], 'job_title' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'role' => ['required', Rule::in(['employee', 'admin'])], 'is_active' => ['required', 'boolean'],
            'report_scope' => ['required', Rule::in(['none', 'department'])], 'can_export_reports' => ['required', 'boolean'],
            'password' => $id ? ['missing'] : ['required', 'string', 'min:12', 'max:255', 'confirmed']];
    }

    public function messages(): array
    {
        return ['*.required' => 'هذا الحقل مطلوب.', '*.unique' => 'القيمة مسجلة لحساب آخر.', 'password.min' => 'كلمة المرور يجب أن تكون 12 حرفًا على الأقل.', 'password.missing' => 'استخدم رابط استعادة كلمة المرور.'];
    }
}
