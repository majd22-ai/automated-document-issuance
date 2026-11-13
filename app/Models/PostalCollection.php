<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostalCollection extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['operation_token', 'command_hash'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'collected_at' => 'immutable_datetime'];
    }

    public function charge()
    {
        return $this->belongsTo(RequestCharge::class, 'request_charge_id');
    }

    public function collector()
    {
        return $this->belongsTo(PostalCollector::class, 'postal_collector_id');
    }

    public function office()
    {
        return $this->belongsTo(PostalOffice::class, 'postal_office_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Postal receipts are immutable'));
        static::deleting(fn () => throw new \LogicException('Postal history is retained'));
    }
}
