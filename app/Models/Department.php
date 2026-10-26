<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employees()
    {
        return $this->hasMany(User::class);
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Departments are retained; deactivate instead'));
    }
}
