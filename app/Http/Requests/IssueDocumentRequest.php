<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === 'employee';
    }

    public function rules(): array
    {
        return ['operation_token' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'expected_rules_version' => ['required', 'integer', 'min:1'], 'expected_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'previous_document_id' => ['nullable', 'integer', 'min:1'],
            'document_number' => ['missing'], 'issued_at' => ['missing'], 'expires_at' => ['missing'], 'status' => ['missing'], 'user_id' => ['missing'], 'issued_by' => ['missing'], 'government_service_id' => ['missing']];
    }
}
