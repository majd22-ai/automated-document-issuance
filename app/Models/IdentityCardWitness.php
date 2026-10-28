<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityCardWitness extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function form()
    {
        return $this->belongsTo(IdentityCardForm::class, 'identity_card_form_id');
    }
}
