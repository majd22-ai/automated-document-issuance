<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostalOffice extends Model
{
    protected $fillable = ['name', 'code', 'area', 'address', 'status'];

    protected static function booted(): void
    {
        static::updated(function (self $office) {
            if ($office->wasChanged(['status', 'is_demo'])) {
                $office->collectors()->increment('auth_version');
            }
        });
    }

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }

    public function collectors(): HasMany
    {
        return $this->hasMany(PostalCollector::class);
    }
}
