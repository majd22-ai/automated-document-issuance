<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeighborhoodOfficial extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['national_id', 'national_id_hash', 'phone'];

    protected function casts(): array
    {
        return ['national_id' => 'encrypted', 'phone' => 'encrypted', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $official) {
            if ($official->isDirty('national_id')) {
                $official->national_id_hash = hash('sha256', $official->national_id);
            }
        });
    }

    public function district()
    {
        return $this->belongsTo(ResidenceDistrict::class, 'district_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function authorized(User $user): bool
    {
        return $user->is_active && $user->role === 'employee'
            && static::where('user_id', $user->id)->where('is_active', true)->where('can_attest', true)->exists();
    }

    public static function residenceOnly(User $user): bool
    {
        return $user->role === 'employee' && static::where('user_id', $user->id)->exists()
            && $user->report_scope !== 'department'
            && ! $user->reviewPermissions()->where(function ($query) {
                foreach (['can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate'] as $ability) {
                    $query->orWhere($ability, true);
                }
            })->exists();
    }
}
