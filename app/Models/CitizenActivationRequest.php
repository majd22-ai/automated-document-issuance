<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitizenActivationRequest extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function citizen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
