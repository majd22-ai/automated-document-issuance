<?php

namespace App\Http\Requests;

use App\Models\DemoCivilPerson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemoCivilPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return config('demo_civil_registry.enabled') === true
            && ($this->user()?->can('manage', DemoCivilPerson::class) ?? false);
    }

    public function rules(): array
    {
        $person = $this->route('person');

        return [
            'demo_identifier' => ['required', 'string', 'regex:/^DEMO-CIV-[0-9]{6}$/D', Rule::unique('demo_civil_people', 'demo_identifier')->ignore($person?->id)],
            'full_name' => ['required', 'string', 'max:180'],
            'gender' => ['required', Rule::in(['male', 'female', 'unspecified'])],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'birth_place' => ['required', 'string', 'max:160'],
            'marital_status' => ['required', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'synthetic_address' => ['required', 'string', 'max:240'],
        ];
    }
}