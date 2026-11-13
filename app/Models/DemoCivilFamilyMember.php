<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoCivilFamilyMember extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['joined_on' => 'date'];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(DemoCivilFamily::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(DemoCivilPerson::class);
    }
}