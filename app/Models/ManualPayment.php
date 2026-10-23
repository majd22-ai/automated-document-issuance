<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualPayment extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'immutable_date', 'recorded_at' => 'immutable_datetime'];
    }

    public function charge()
    {
        return $this->belongsTo(RequestCharge::class, 'request_charge_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Successful receipts are immutable'));
        static::deleting(fn () => throw new \LogicException('Successful receipts are retained'));
    }
}
