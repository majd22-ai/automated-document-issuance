<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public function documents(): HasMany
    {
        return $this->hasMany(CitizenDocument::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CitizenAttachment::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(CitizenServiceRequest::class);
    }

    public function missingCitizenFields(): array
    {
        $missing = [];
        foreach (['name' => 'الاسم الكامل', 'username' => 'الرقم الوطني', 'email' => 'البريد الإلكتروني'] as $field => $label) {
            if (trim((string) $this->{$field}) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public const ROLES = ['citizen', 'employee', 'admin'];

    protected $attributes = ['role' => 'citizen', 'is_active' => true];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
