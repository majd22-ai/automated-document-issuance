<?php

namespace App\Services\Review;

use App\Models\CitizenServiceRequest;
use App\Models\RequestDeficiency;
use App\Models\User;
use App\Services\Operations\LockedRequest;
use Illuminate\Validation\ValidationException;

class ReviewAdministration
{
    public function __construct(private LockedRequest $locks, private ReviewActions $actions) {}

    public function assign(User $actor, CitizenServiceRequest $request, array $data): void
    {
        $this->locks->run($actor, $request, 'assignReview', function ($actor, $owner, $service, $request) use ($data) {
            $context = array_merge($data, ['internal_notes' => $data['reason']]);
            if ($this->actions->replay($request, $actor, 'assign', null, $context)) {
                return;
            }
            $this->actions->assertCurrent($request, $context);
            if (! in_array($request->status, ['submitted', 'under_review', 'requires_correction'], true)) {
                throw ValidationException::withMessages(['assignment' => 'الإسناد متاح قبل انتهاء المراجعة فقط.']);
            }
            $target = User::findOrFail($data['assigned_to']);
            if (! $target->canReviewService($service->id, 'can_review')) {
                throw ValidationException::withMessages(['assigned_to' => 'الموظف المحدد غير مخول بمراجعة هذه الخدمة.']);
            }
            $previous = $request->assigned_to;
            $request->forceFill(['assigned_to' => $target->id, 'assigned_by' => $actor->id, 'assigned_at' => now(), 'review_version' => $request->review_version + 1])->save();
            $this->actions->save($request, $actor, 'assign', $request->status, null, $context, details: ['previous_assignee' => $previous, 'new_assignee' => $target->id]);
        });
    }

    public function resolve(User $actor, CitizenServiceRequest $request, RequestDeficiency $deficiency, array $data): void
    {
        $this->locks->run($actor, $request, 'resolveReview', function ($actor, $owner, $service, $request) use ($deficiency, $data) {
            $row = $request->deficiencies()->whereKey($deficiency->id)->lockForUpdate()->firstOrFail();
            if ($row->status === 'resolved' && $row->resolved_by === $actor->id && $row->resolution_note === $data['resolution_note']) {
                return;
            }
            $this->actions->assertCurrent($request, $data);
            if ($row->status !== 'awaiting_review') {
                throw ValidationException::withMessages(['deficiencies' => 'انتظر إعادة تقديم المواطن واستكماله للنقص قبل تسجيل التحقق.']);
            }
            app(ReviewDeficiencies::class)->assertResolution($request, $row);
            $row->forceFill(['status' => 'resolved', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'resolution_note' => $data['resolution_note']])->save();
            $request->forceFill(['review_version' => $request->review_version + 1])->save();
            $this->actions->save($request, $actor, 'resolve', $request->status, null, ['internal_notes' => $data['resolution_note']], details: ['deficiency_id' => $row->id]);
        });
    }
}
