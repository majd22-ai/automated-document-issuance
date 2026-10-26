<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmCitizenAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('activateCitizen', $this->route('citizen')) ?? false;
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['activate', 'confirm_issuance'])],
            'national_id' => ['required', 'string', 'regex:/^[0-9]{5,20}$/'],
            'request_id' => ['nullable', 'required_if:action,confirm_issuance', 'prohibited_if:action,activate', 'integer', 'min:1'],
            'identity_confirmed' => ['required', 'boolean', 'accepted_if:action,confirm_issuance'],
            'official_issuance_confirmed' => ['required', 'boolean', 'accepted_if:action,confirm_issuance', 'declined_if:action,activate'],
            'evidence_reference' => ['required', 'string', 'min:8', 'max:255'],
            'operation_token' => ['required', 'uuid'],
            'is_active' => ['missing'], 'role' => ['missing'], 'account_activated_at' => ['missing'], 'identity_verified_at' => ['missing'], 'registration_status' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return ['*.required' => 'هذا الحقل مطلوب.', 'national_id.regex' => 'أدخل الرقم الوطني الرسمي من 5 إلى 20 رقمًا.',
            '*.accepted_if' => 'يلزم تأكيد اكتمال التحقق الرسمي وإصدار البطاقة لهذا الإجراء.',
            'request_id.required_if' => 'حدد طلب البطاقة المكتمل المرتبط بهذا الحساب.',
            'evidence_reference.min' => 'أدخل مرجع تحقق رسميًا واضحًا لا يقل عن 8 خانات.',
            '*.prohibited_if' => 'هذا الحقل غير مسموح في الإجراء المحدد.', '*.declined_if' => 'تفعيل الحساب وحده لا يعني إصدار بطاقة.',
            '*.missing' => 'لا يمكن تعيين حالة الحساب أو الصلاحيات مباشرة.'];
    }
}
