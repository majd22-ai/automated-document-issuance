<?php

namespace App\Services\Review;

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\ServiceEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Read-only integration point for the future issuance workflow. Does not issue documents. */
class ApprovalReadiness
{
    public function check(CitizenServiceRequest $application): array
    {
        return DB::transaction(function () use ($application) {
            $citizen = User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
            $service = GovernmentService::whereKey($application->government_service_id)->lockForUpdate()->firstOrFail();
            $request = CitizenServiceRequest::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($request->user_id !== $citizen->id || $request->government_service_id !== $service->id
                || $request->status !== 'approved' || ! $request->reviewDecisions()->where('action', 'approve')->exists()) {
                throw ValidationException::withMessages(['review' => 'الطلب يحتاج اعتمادًا نهائيًا موثقًا قبل مرحلة الإصدار.']);
            }
            app(ReviewDeficiencies::class)->assertResolved($request);
            $report = app(ServiceEligibility::class)->evaluate($citizen, $service, $request, ServiceRequestStatus::Processing);
            if (! $report->eligible()) {
                throw ValidationException::withMessages(['requirements' => $report->reasons()]);
            }

            return $report->toArray();
        });
    }
}
