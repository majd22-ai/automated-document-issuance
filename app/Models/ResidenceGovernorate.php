<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidenceGovernorate extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    public function districts()
    {
        return $this->hasMany(ResidenceDistrict::class, 'governorate_id');
    }
}
