<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDecisionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('deficiencies'))) {
            $this->merge(['deficiencies' => array_values(array_filter($this->input('deficiencies'), fn ($row) => ! is_array($row) || filled($row['target_key'] ?? null) || filled($row['description'] ?? null)))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === 'employee';
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['start', 'accept', 'return', 'reject', 'approve'])], 'decision_token' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'expected_rules_version' => ['required', 'integer', 'min:1'], 'expected_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'public_reason' => ['required_if:action,return,reject', 'nullable', 'string', 'min:3', 'max:2000'], 'internal_notes' => ['nullable', 'string', 'max:2000'],
            'deficiencies' => ['required_if:action,return', 'sometimes', 'array', 'min:1', 'max:20'], 'deficiencies.*' => ['array:target_type,target_key,description'], 'deficiencies.*.target_type' => ['required', Rule::in(['field', 'attachment', 'requirement', 'other'])], 'deficiencies.*.target_key' => ['required', 'string', 'max:100'], 'deficiencies.*.description' => ['required', 'string', 'min:3', 'max:500'],
            'actor_id' => ['missing'], 'user_id' => ['missing'], 'status' => ['missing'], 'eligible' => ['missing'], 'requirements_result' => ['missing'], 'assigned_to' => ['missing']];
    }

    public function messages(): array
    {
        return ['public_reason.required_if' => 'سبب واضح للرفض أو الإرجاع مطلوب.', 'deficiencies.required_if' => 'حدد النواقص المطلوب استكمالها.', '*.missing' => 'لا يمكن إرسال هوية المنفذ أو الحالة أو نتيجة الأهلية من المتصفح.'];
    }
}
