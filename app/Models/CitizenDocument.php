<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitizenDocument extends Model
{
    protected $fillable = ['document_type', 'document_type_code', 'document_number', 'status', 'issued_at', 'expires_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date'];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
