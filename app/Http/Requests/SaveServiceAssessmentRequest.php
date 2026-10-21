<?php

namespace App\Http\Requests;

use App\Models\GovernmentService;
use Illuminate\Foundation\Http\FormRequest;

class SaveServiceAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', GovernmentService::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'identity_verified' => ['required', 'boolean'], 'conditions_met' => ['required', 'boolean'],
            'age_requirement_met' => ['sometimes', 'boolean'], 'driving_requirements_met' => ['sometimes', 'boolean'],
            'is_blocked' => ['required', 'boolean'],
            'block_reason' => ['nullable', 'required_if:is_blocked,1,true', 'string', 'max:255'],
            'public_block_reason' => ['nullable', 'string', 'max:255'],
            'verification_reference' => ['required', 'string', 'max:255'],
            'expected_rules_version' => ['required', 'integer', 'min:1'],
            'rules_version' => ['missing'], 'user_id' => ['missing'], 'reviewed_by' => ['missing'], 'reviewed_at' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return ['verification_reference.required' => 'مرجع التحقق المعتمد مطلوب؛ لا تسجّل تقييمًا دون مستند موثوق.', 'username.required' => 'الرقم الوطني للمواطن مطلوب.', 'block_reason.required_if' => 'يجب تسجيل سبب المانع المعتمد.'];
    }
}
