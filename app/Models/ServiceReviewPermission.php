<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceReviewPermission extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['can_review' => 'boolean', 'can_approve' => 'boolean', 'can_assign' => 'boolean', 'can_issue' => 'boolean', 'can_deliver' => 'boolean', 'can_verify' => 'boolean', 'can_activate' => 'boolean'];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }
}
