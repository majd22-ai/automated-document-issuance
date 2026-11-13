<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoCivilPerson extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['photo_path', 'photo_sha256', 'photo_byte_size'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'archived_at' => 'datetime', 'is_synthetic' => 'boolean', 'photo_byte_size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $person) => $person->is_synthetic = true);
    }

    public function records(): HasMany
    {
        return $this->hasMany(DemoCivilRecord::class, 'person_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DemoCivilDocument::class, 'person_id');
    }

    public function familyMemberships(): HasMany
    {
        return $this->hasMany(DemoCivilFamilyMember::class, 'person_id');
    }

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(DemoCivilFamily::class, 'demo_civil_family_members', 'person_id', 'family_id')
            ->withPivot(['relationship', 'status', 'joined_on'])->withTimestamps();
    }

    public function parties(): HasMany
    {
        return $this->hasMany(DemoCivilRecordParty::class, 'person_id');
    }
}