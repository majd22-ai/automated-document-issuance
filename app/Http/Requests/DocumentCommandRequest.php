<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    public function rules(): array
    {
        $rules = ['operation_token' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'document_number' => ['missing'], 'user_id' => ['missing'], 'delivered_by' => ['missing'], 'delivered_at' => ['missing']];
        if (str_ends_with($this->route()->getName(), '.delivery.choose')) {
            return array_merge($rules, ['method' => ['required', Rule::in(['pickup', 'address'])], 'center' => ['nullable', 'string', 'max:200'], 'address' => ['nullable', 'string', 'min:10', 'max:1000'], 'status' => ['missing'], 'proof_reference' => ['missing']]);
        }
        if (str_ends_with($this->route()->getName(), '.delivery.status')) {
            return array_merge($rules, ['status' => ['required', Rule::in(['ready', 'preparing', 'out_for_delivery', 'delivered', 'failed', 'returned'])], 'reason' => ['nullable', 'string', 'min:3', 'max:500'], 'proof_reference' => ['nullable', 'string', 'min:3', 'max:150']]);
        }

        return array_merge($rules, ['reason' => ['required', 'string', 'min:3', 'max:500'], 'status' => ['missing']]);
    }
}
