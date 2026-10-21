<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCitizenAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('attachment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'uuid'],
            'review_status' => ['required', Rule::in(['approved', 'rejected', 'replacement_required'])],
            'public_reason' => ['nullable', 'required_if:review_status,rejected,replacement_required', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
