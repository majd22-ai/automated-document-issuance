<?php

namespace App\Http\Requests;

use App\Models\CitizenServiceRequest;
use Illuminate\Foundation\Http\FormRequest;

class SubmitServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CitizenServiceRequest::class) ?? false;
    }

    public function rules(): array
    {
        $rules = ['preview_token' => ['required', 'uuid'], 'declaration' => ['required', 'accepted']];
        foreach (['data', 'notes', 'user_id', 'citizen_id', 'government_service_id', 'service_id', 'status', 'reference', 'request_type', 'submission_token', 'active_request_key'] as $field) {
            $rules[$field] = ['missing'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['declaration.required' => 'الإقرار بصحة البيانات مطلوب قبل الإرسال النهائي.', 'declaration.accepted' => 'يجب الموافقة على الإقرار بصحة البيانات.', 'preview_token.required' => 'راجع ملخص الطلب أولًا.', '*.missing' => 'لا يمكن تغيير بيانات الملخص عند التأكيد؛ ارجع إلى نموذج الطلب لتعديلها.'];
    }
}
