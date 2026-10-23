<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordMedicalExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'employee' && $this->user()->is_active;
    }

    public function rules(): array
    {
        return ['expected_id' => ['required', 'integer', 'min:0'], 'expected_rules_version' => ['required', 'integer', 'min:1'], 'result' => ['required', Rule::in(['passed', 'failed', 'pending', 'retest'])],
            'examined_at' => ['required_if:result,passed,failed,retest', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:examined_at'], 'source_reference' => ['required', 'string', 'max:255'],
            'private_notes' => ['nullable', 'string', 'max:2000'], 'citizen_appointment_id' => ['nullable', 'integer', 'min:1'], 'recorded_by' => ['missing'], 'user_id' => ['missing'], 'rules_version' => ['missing']];
    }

    public function messages(): array
    {
        return ['source_reference.required' => 'مرجع نتيجة الفحص من الجهة المختصة مطلوب.', 'examined_at.required_if' => 'تاريخ الفحص مطلوب لهذه النتيجة.', '*.missing' => 'لا يمكن تعيين بيانات التوثيق أو الملكية يدويًا.'];
    }
}
