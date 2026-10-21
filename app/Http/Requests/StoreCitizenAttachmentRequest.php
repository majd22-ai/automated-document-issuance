<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCitizenAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === 'citizen';
    }

    public function rules(): array
    {
        return [
            'document_code' => ['required', 'string', 'max:40'], 'file' => ['required', 'file'],
            'user_id' => ['missing'], 'citizen_id' => ['missing'], 'request_id' => ['missing'], 'citizen_service_request_id' => ['missing'],
            'government_service_id' => ['missing'], 'storage_path' => ['missing'], 'disk' => ['missing'], 'review_status' => ['missing'],
            'reviewed_by' => ['missing'], 'reviewed_at' => ['missing'], 'expires_at' => ['missing'], 'sha256' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return ['file.required' => 'اختر ملفًا واحدًا لهذا المستند.', 'file.file' => 'الملف غير صالح؛ اختر ملفًا واحدًا فقط.', '*.missing' => 'لا يمكن تعيين الملكية أو مسار التخزين أو بيانات المراجعة من نموذج الرفع.'];
    }
}
