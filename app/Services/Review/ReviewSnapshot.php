<?php

namespace App\Services\Review;

use App\Models\CitizenServiceRequest;

class ReviewSnapshot
{
    public function fingerprint(CitizenServiceRequest $request): string
    {
        $request = $request->fresh();
        $values = [
            $request->only(['id', 'status', 'review_version', 'assigned_to', 'request_data', 'notes', 'service_snapshot']),
            $request->citizen?->only(['name', 'username', 'email', 'phone', 'role', 'is_active']),
            $request->citizen?->documents()->orderBy('id')->get()->toArray(),
            $request->service?->submissionFingerprint(),
            $request->service?->assessments()->where('user_id', $request->user_id)->first()?->getAttributes(),
            $request->attachments()->orderBy('id')->get()->map(fn ($file) => $file->getAttributes())->all(),
            $request->appointments()->with('slot')->orderBy('id')->get()->toArray(),
            $request->examinations()->orderBy('id')->get()->map(fn ($exam) => $exam->getAttributes())->all(),
            $request->charge()->with('payment')->first()?->toArray(),
            $request->deficiencies()->orderBy('id')->get()->toArray(),
        ];

        // Only the digest leaves this service; raw values and private notes never enter Logs.
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }
}
