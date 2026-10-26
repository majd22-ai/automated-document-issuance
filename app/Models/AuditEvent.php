<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit is append only'));
        static::deleting(fn () => throw new \LogicException('Audit history is retained'));
    }
}
