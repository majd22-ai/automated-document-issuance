<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReviewDeficiencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === 'employee';
    }

    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:0'], 'expected_rules_version' => ['required', 'integer', 'min:1'], 'expected_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'], 'resolution_note' => ['required', 'string', 'min:3', 'max:500']];
    }
}
