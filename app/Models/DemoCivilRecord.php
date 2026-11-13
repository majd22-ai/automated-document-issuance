<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoCivilRecord extends Model
{
    public const TYPES = ['birth', 'marriage', 'divorce', 'death', 'identity_card', 'family', 'correction', 'restriction'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'details' => 'array', 'archived_at' => 'datetime', 'is_synthetic' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $record) => $record->is_synthetic = true);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(DemoCivilPerson::class, 'person_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(DemoCivilFamily::class, 'family_id');
    }

    public function relatedRecord(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_record_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(DemoCivilRecordParty::class, 'record_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DemoCivilDocument::class, 'record_id');
    }
}