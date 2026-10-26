<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitizenAccountConfirmation extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['evidence_reference', 'payload_fingerprint', 'operation_token'];

    protected function casts(): array
    {
        return ['evidence_reference' => 'encrypted', 'identity_confirmed' => 'boolean', 'confirmed_at' => 'immutable_datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Account confirmations are immutable'));
        static::deleting(fn () => throw new \LogicException('Account confirmations are retained'));
    }
}
