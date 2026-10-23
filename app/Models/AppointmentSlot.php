<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSlot extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'capacity' => 'integer', 'is_active' => 'boolean'];
    }

    public function service()
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function bookings()
    {
        return $this->hasMany(CitizenAppointment::class);
    }

    public function occupancy(): int
    {
        return $this->bookings()->whereIn('status', ['booked', 'confirmed', 'completed', 'no_show'])->count();
    }

    public function available(): bool
    {
        return $this->is_active && $this->starts_at->isFuture() && $this->occupancy() < $this->capacity;
    }
}
