<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeighborhoodOfficial extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['national_id', 'national_id_hash', 'phone'];

    protected function casts(): array
    {
        return ['national_id' => 'encrypted', 'phone' => 'encrypted', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $official) {
            if ($official->isDirty('national_id')) {
                $official->national_id_hash = hash('sha256', $official->national_id);
            }
        });
    }

    public function district()
    {
        return $this->belongsTo(ResidenceDistrict::class, 'district_id');
    }
}
