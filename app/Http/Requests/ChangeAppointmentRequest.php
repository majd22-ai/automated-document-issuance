<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view', $this->route('appointment')->application);
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in($this->user()->role === 'employee' ? ['cancel', 'reschedule', 'confirmed', 'completed', 'no_show'] : ['cancel', 'reschedule'])], 'slot_id' => ['required_if:action,reschedule', 'nullable', 'integer', 'min:1'], 'status' => ['missing'], 'user_id' => ['missing']];
    }
}
