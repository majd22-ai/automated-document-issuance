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
        return ['authority_type' => ['required', 'in:neighborhood'],
            'governorate_id' => ['required', 'integer', 'exists:residence_governorates,id'],
            'district_id' => ['required', 'integer', 'exists:residence_districts,id'],
            'official_id' => ['required', 'integer', 'exists:neighborhood_officials,id'],
            'neighborhood' => ['required', 'string', 'max:150']];
    }

    public function messages(): array
    {
        return ['*.required' => 'هذا الحقل مطلوب.', '*.exists' => 'اختر قيمة مسجلة في النظام.', 'authority_type.in' => 'المصادقة الجديدة متاحة عن طريق عاقل الحارة فقط.'];
    }
}
