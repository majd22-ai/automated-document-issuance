<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordManualPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('receipt_reference'))) {
            $this->merge(['receipt_reference' => mb_strtoupper(trim($this->input('receipt_reference')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'employee' && $this->user()->is_active;
    }

    public function rules(): array
    {
        return ['amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,2})?$/D'], 'currency' => ['required', 'string', 'max:20'],
            'receipt_reference' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[A-Z0-9][A-Z0-9._:\/-]+$/D'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'method' => ['required', Rule::in(['cash', 'bank_transfer'])],
            'status' => ['missing'], 'user_id' => ['missing'], 'recorded_by' => ['missing']];
    }

    public function messages(): array
    {
        return ['receipt_reference.required' => 'مرجع الإيصال الموثق مطلوب.', 'amount.regex' => 'أدخل المبلغ بدقة منزلتين عشريتين دون فواصل أو أرقام أسية.', '*.missing' => 'لا يمكن تعيين حالة السداد أو الملكية مباشرة.'];
    }
}
