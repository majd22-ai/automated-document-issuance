<?php

namespace App\Http\Controllers;

use App\Models\IdentityCardForm;
use App\Models\IdentityVisitEvidence;
use App\Services\FingerprintService;
use App\Services\Identity\IdentityJourney;
use App\Services\Identity\VisitEvidence;
use App\Services\Operations\LockedRequest;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class IdentityJourneyController extends Controller
{
    public function store(Request $request, IdentityCardForm $form, VisitEvidence $evidence)
    {
        $data = $request->validate(['version' => ['required', 'integer'], 'kind' => ['required', 'in:blood_result,card_photo'], 'file' => ['required', 'file', 'max:5120']]);
        $evidence->store($request->user(), $form, $data['kind'], $request->file('file'), $data['version']);

        return back()->with('status', 'حُفظ الدليل الخاص؛ يلزم مراجعته قبل البصمة والاعتماد.');
    }

    public function review(Request $request, IdentityVisitEvidence $evidence, VisitEvidence $service)
    {
        $data = $request->validate(['version' => ['required', 'integer'], 'status' => ['required', 'in:approved,rejected,requires_correction'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $service->review($request->user(), $evidence, $data['status'], $data['reason'] ?? null, $data['version']);

        return back()->with('status', 'تم توثيق مراجعة دليل الزيارة.');
    }

    public function file(Request $request, IdentityVisitEvidence $evidence)
    {
        $application = $evidence->form->application;
        Gate::authorize('viewReview', $application);
        abort_unless(IdentityJourney::applies($application) && $evidence->attachment->intact(), 404);

        return response()->file(Storage::disk('attachments')->path($evidence->attachment->storage_path), ['Content-Type' => $evidence->attachment->mime_type, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    public function witnesses(Request $request, IdentityCardForm $form)
    {
        abort_unless(FingerprintService::enabled(), 403);
        $data = $request->validate(['version' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:3', 'max:1000'], 'present_and_verified' => ['sometimes', 'accepted']]);
        app(LockedRequest::class)->run($request->user(), $form->application, 'recordOperations', function ($actor, $owner, $service, $application) use ($form, $data) {
            $form = IdentityCardForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            abort_unless(IdentityJourney::applies($application) && $form->version === (int) $data['version'] && in_array($application->status, ['submitted', 'under_review', 'accepted'], true) && $form->status !== 'demo_completed' && ! $application->issuedDocument()->exists(), 409);
            abort_if($form->operations()->where('form_revision', $form->revision)->where('result', 'success')->exists(), 409);
            if (! empty($data['present_and_verified'])) {
                abort_unless($form->arrived_at && $form->witness_attendance_reason && $form->witnesses()->count() === 2, 409);
                $form->witnesses()->update(['attendance_status' => 'present', 'verification_status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $data['reason']]);
            } else {
                $form->forceFill(['witness_attendance_reason' => $data['reason'], 'witness_attendance_requested_by' => $actor->id, 'witness_attendance_requested_at' => now()]);
                app(IdentityJourney::class)->notify($owner, $application, 'witnesses:'.$form->id.':'.$form->version, 'طلب حضور الشاهدين', 'يلزم حضور الشاهدين بسبب: '.$data['reason']);
            }
            $form->forceFill(['version' => $form->version + 1])->save();
            app(AuditTrail::class)->record('identity_witness_attendance', IdentityCardForm::class, $form->id, $actor, changes: ['action' => empty($data['present_and_verified']) ? 'requested' : 'verified']);
        });

        return back()->with('status', 'حُفظ إجراء الشاهدين مع السبب.');
    }
}
