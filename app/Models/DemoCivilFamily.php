<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoCivilFamily extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'is_synthetic' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $family) => $family->is_synthetic = true);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(DemoCivilPerson::class, 'head_person_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(DemoCivilPerson::class, 'demo_civil_family_members', 'family_id', 'person_id')
            ->withPivot(['relationship', 'status', 'joined_on'])->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(DemoCivilFamilyMember::class, 'family_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(DemoCivilRecord::class, 'family_id');
    }
}