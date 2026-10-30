<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideIdentityAttestation extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('simulate', $this->route('attestation')->form) ?? false;
    }

    public function rules(): array
    {
        $rules = ['decision' => ['required', 'in:approved,rejected'], 'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1500']];
        if ($this->route('attestation')->neighborhood_official_id && $this->input('decision') === 'approved') {
            $rules['verification_method'] = ['required', 'in:code,fingerprint'];
            $rules['verification_code'] = ['required_if:verification_method,code', 'nullable', 'digits:6'];
        }
        if ($this->route('attestation')->authority_type === 'witnesses') {
            $approve = $this->input('decision') === 'approved';
            $rules += ['witnesses' => ['required', 'array', 'list', 'size:2'],
                'witnesses.*' => ['array:attendance,verification,note'],
                'witnesses.*.attendance' => ['required', $approve ? 'in:present' : 'in:present,absent'],
                'witnesses.*.verification' => ['required', $approve ? 'in:approved' : 'in:approved,rejected'],
                'witnesses.*.note' => ['nullable', 'string', 'max:500']];
        } else {
            $rules['witnesses'] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['*.required' => 'هذا الحقل مطلوب.', 'decision.in' => 'اختر الموافقة أو الرفض.', 'reason.required_if' => 'أدخل سبب الرفض.', 'reason.max' => 'سبب الرفض لا يزيد عن 1500 حرف.', 'witnesses.*.attendance.in' => 'الموافقة تتطلب حضور الشاهدين.', 'witnesses.*.verification.in' => 'الموافقة تتطلب التحقق من وثائق الشاهدين وقبولها.', 'witnesses.prohibited' => 'نتيجة الشهود غير متاحة في مسار الجهة المختار.'];
    }
}
