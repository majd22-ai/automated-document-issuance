<?php

namespace App\Http\Requests;

use App\Models\DemoCivilPerson;
use Illuminate\Foundation\Http\FormRequest;

class DemoCivilDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return config('demo_civil_registry.enabled') === true
            && ($this->user()?->can('manage', DemoCivilPerson::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', 'exists:demo_civil_people,id'],
            'record_id' => ['nullable', 'integer', 'exists:demo_civil_records,id'],
            'label' => ['required', 'string', 'max:160'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}