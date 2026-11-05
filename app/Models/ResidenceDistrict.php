<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidenceDistrict extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    public function governorate()
    {
        return $this->belongsTo(ResidenceGovernorate::class, 'governorate_id');
    }

    public function officials()
    {
        return $this->hasMany(NeighborhoodOfficial::class, 'district_id');
    }
}
