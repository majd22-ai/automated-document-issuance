<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestStatus;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && $u->is_active && ($u->role === 'admin' || ($u->role === 'employee' && $u->report_scope === 'department' && Department::whereKey($u->department_id)->where('is_active', true)->exists()));
    }

    public function rules(): array
    {
        return ['period' => ['sometimes', 'in:today,7days,30days,month,custom'], 'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'], 'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'employee_id' => ['nullable', 'integer', 'exists:users,id'], 'service_id' => ['nullable', 'integer', 'exists:government_services,id'], 'status' => ['nullable', Rule::in(array_column(ServiceRequestStatus::cases(), 'value'))], 'report' => ['sometimes', 'in:requests,employees,documents,payments,integrations,departments'], 'department_page' => ['sometimes', 'integer', 'min:1'], 'page' => ['sometimes', 'integer', 'min:1']];
    }
}
