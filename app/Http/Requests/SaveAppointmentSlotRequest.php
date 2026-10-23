<?php

namespace App\Http\Requests;

use App\Models\AppointmentSlot;
use Illuminate\Foundation\Http\FormRequest;

class SaveAppointmentSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', AppointmentSlot::class) ?? false;
    }

    public function rules(): array
    {
        $editing = $this->route('slot') instanceof AppointmentSlot;

        return ['government_service_id' => $editing ? ['missing'] : ['required', 'integer', 'exists:government_services,id'], 'center' => $editing ? ['missing'] : ['required', 'string', 'max:200'], 'starts_at' => $editing ? ['missing'] : ['required', 'date_format:Y-m-d\TH:i'], 'ends_at' => $editing ? ['missing'] : ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'], 'capacity' => ['required', 'integer', 'min:1', 'max:1000'], 'is_active' => ['sometimes', 'boolean']];
    }
}
