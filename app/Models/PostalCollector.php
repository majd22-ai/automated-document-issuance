<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PostalCollector extends Authenticatable
{
    protected $fillable = ['postal_office_id', 'name', 'username', 'password', 'is_active', 'can_collect'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'can_collect' => 'boolean', 'auth_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $collector) {
            if ($collector->isDirty(['password', 'username', 'postal_office_id', 'is_active', 'can_collect'])) {
                $collector->auth_version = $collector->getOriginal('auth_version') + 1;
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(PostalOffice::class, 'postal_office_id');
    }

    public function canAccessPostalWorkspace(): bool
    {
        return $this->is_active && $this->can_collect && $this->office?->is_demo
            && $this->office->status === 'active';
    }
}
