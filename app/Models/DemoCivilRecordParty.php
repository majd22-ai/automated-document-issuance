<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoCivilRecordParty extends Model
{
    protected $guarded = ['id'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(DemoCivilRecord::class, 'record_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(DemoCivilPerson::class, 'person_id');
    }
}