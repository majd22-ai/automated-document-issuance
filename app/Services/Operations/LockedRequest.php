<?php

namespace App\Services\Operations;

use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Notifications\OperationalNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class LockedRequest
{
    public function run(User $actor, CitizenServiceRequest $application, string $ability, callable $work): mixed
    {
        return DB::transaction(function () use ($actor, $application, $ability, $work) {
            $owner = User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
            $service = GovernmentService::whereKey($application->government_service_id)->lockForUpdate()->firstOrFail();
            $request = CitizenServiceRequest::whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->user_id === $owner->id && $request->government_service_id === $service->id, 409);
            $actor = $actor->id === $owner->id ? $owner : $actor->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize($ability, $request);

            return app(OperationalNotifications::class)->asActor($actor, fn () => $work($actor, $owner, $service, $request));
        });
    }

    public function invalidate(CitizenServiceRequest $request): void
    {
        $request->forceFill(['requirements_result' => null, 'requirements_checked_at' => null])->save();
    }

    public function audit(string $action, int $record, CitizenServiceRequest $request, User $actor): void
    {
        DB::afterCommit(function () use ($action, $record, $request, $actor) {
            try {
                Log::info('request_operation', ['action' => $action, 'record_id' => $record, 'request_id' => $request->id, 'actor_id' => $actor->id]);
            } catch (\Throwable) {
            }
        });
    }
}
