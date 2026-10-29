<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivationFingerprintAttempt extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['visit_token', 'operation_token'];

    protected function casts(): array
    {
        return ['performed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Activation attempts are immutable'));
        static::deleting(fn () => throw new \LogicException('Activation attempts are retained'));
    }
}
