<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === 'employee';
    }

    public function rules(): array
    {
        return ['assigned_to' => ['required', 'integer', 'exists:users,id'], 'expected_version' => ['required', 'integer', 'min:0'], 'decision_token' => ['required', 'uuid'], 'reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
