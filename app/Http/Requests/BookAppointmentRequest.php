<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    public function rules(): array
    {
        return ['slot_id' => ['required', 'integer', 'min:1'], 'user_id' => ['missing'], 'status' => ['missing'], 'amount' => ['missing']];
    }

    public function messages(): array
    {
        return ['slot_id.required' => 'اختر موعدًا متاحًا.', '*.missing' => 'لا يمكن تعيين الملكية أو الحالة أو المبلغ من نموذج الحجز.'];
    }
}
