<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitReviewedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === 'citizen';
    }

    public function rules(): array
    {
        return ['action' => ['sometimes', 'in:resubmit'], 'decision_token' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:0'], 'expected_rules_version' => ['required', 'integer', 'min:1'], 'expected_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'data' => ['sometimes', 'array', 'max:20'], 'responses' => ['required', 'array', 'max:100'], 'responses.*' => ['required', 'string', 'min:3', 'max:500'], 'status' => ['missing'], 'user_id' => ['missing'], 'actor_id' => ['missing'], 'internal_notes' => ['missing'], 'public_reason' => ['missing'], 'deficiencies' => ['missing']];
    }
}
