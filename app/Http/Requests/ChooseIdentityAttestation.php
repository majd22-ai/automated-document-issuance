<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChooseIdentityAttestation extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chooseAttestation', $this->route('form')) ?? false;
    }

    public function rules(): array
    {
        return ['authority_type' => ['required', 'in:workplace,neighborhood,witnesses'],
            'recipient_name' => ['required_unless:authority_type,witnesses', 'nullable', 'string', 'max:150']];
    }

    public function messages(): array
    {
        return ['authority_type.required' => 'اختر مسار المصادقة.', 'authority_type.in' => 'مسار المصادقة غير صحيح.', 'recipient_name.required_unless' => 'أدخل اسم الجهة المستلمة في المحاكاة.', 'recipient_name.max' => 'اسم الجهة لا يزيد عن 150 حرفًا.'];
    }
}
