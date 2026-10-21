<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitizenServiceAssessment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['block_reason', 'verification_reference'];

    protected function casts(): array
    {
        return ['identity_verified' => 'boolean', 'conditions_met' => 'boolean', 'age_requirement_met' => 'boolean', 'driving_requirements_met' => 'boolean', 'is_blocked' => 'boolean', 'rules_version' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }
}
