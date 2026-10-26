<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationAttempt extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected $attributes = ['attempts' => 0];

    protected $hidden = ['input_fingerprint', 'idempotency_hash', 'active_key'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }

    public function application()
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function citizen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $attempt) {
            if ($attempt->getOriginal('finished_at') || ! in_array($attempt->getOriginal('status'), ['pending', 'in_progress'], true) || $attempt->isDirty(['citizen_service_request_id', 'user_id', 'actor_id', 'target_document_id', 'kind', 'provider', 'origin', 'idempotency_hash', 'input_fingerprint', 'started_at'])) {
                throw new \LogicException('Verification outcomes are retained and immutable');
            }
        });
        static::deleting(fn () => throw new \LogicException('Verification audit retained'));
    }
}
