<?php

namespace Tests\Support;

use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;

trait GrantsWorkspacePermissions
{
    protected function grantWorkspaceServices(User $employee, array $codes, array $abilities = ['can_review']): void
    {
        foreach (GovernmentService::whereIn('code', $codes)->get() as $service) {
            (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'granted_by' => $employee->id] + array_fill_keys($abilities, true))->save();
        }
    }
}
