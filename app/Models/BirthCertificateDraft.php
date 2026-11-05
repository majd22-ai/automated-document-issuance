<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthCertificateDraft extends Model
{
    protected $guarded = ['*'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array'];
    }
}
