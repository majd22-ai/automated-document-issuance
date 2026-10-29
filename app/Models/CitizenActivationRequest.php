<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitizenActivationRequest extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['otp_hash', 'identity_number', 'visit_phone', 'account_binding', 'visit_token'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'identity_number' => 'encrypted', 'visit_phone' => 'encrypted',
            'visit_expires_at' => 'datetime', 'otp_sent_at' => 'datetime', 'otp_expires_at' => 'datetime', 'otp_verified_at' => 'datetime', 'visit_consumed_at' => 'datetime'];
    }

    public function citizen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
