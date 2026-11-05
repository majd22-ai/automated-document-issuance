<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChooseIdentityAttestation;
use App\Http\Requests\DecideIdentityAttestation;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
use App\Services\IdentityAttestations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdentityAttestationController extends Controller
{
    public function store(ChooseIdentityAttestation $request, IdentityCardForm $form, IdentityAttestations $service)
    {
        $service->choose($request->user(), $form, $request->validated());

        return redirect()->route('citizen.identity-card.show', $form)->with('status', 'تمّ اختيار جهة المصادقة وإرسال الطلب. ستُبلَّغ بالنتيجة عند اكتمال إجراءات المصادقة.');
    }

    public function index(Request $request, IdentityAttestations $service)
    {
        abort_unless(IdentityAttestations::enabled(), 404);
        $attestations = $service->visibleTo($request->user())->with(['form.application'])->latest('id')->paginate(20);

        return response()->view('simulation.identity-attestations.index', compact('attestations'))->header('Cache-Control', 'no-store, private');
    }

    public function feed(Request $request, IdentityAttestations $service)
    {
        abort_unless(IdentityAttestations::enabled(), 404);
        $query = $service->visibleTo($request->user())->whereNotNull('notification_created_at');
        $unread = (clone $query)->whereNull('notification_read_at')->count();
        $notifications = $query->with('form.application')->latest('id')->limit(30)->get()->map(fn ($row) => [
            'id' => $row->id, 'recipient_name' => $row->recipient_name,
            'citizen_name' => $row->citizen_name ?? $row->form->data['applicant_name'] ?? 'مواطن مجهول',
            'request_reference' => $row->form->application->reference,
            'authority_label' => $row->authorityLabel(), 'created_at' => $row->notification_created_at->toIso8601String(),
            'read' => $row->notification_read_at !== null, 'open_url' => route('simulation.identity-attestations.open', $row, false),
        ])->values();

        return response()->json(compact('unread', 'notifications'))->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, IdentityCardAttestation $attestation)
    {
        abort_unless(IdentityAttestations::enabled(), 404);
        Gate::authorize('simulate', $attestation->form);
        $attestation->load(['form.application', 'form.witnesses', 'decider']);

        return response()->view('simulation.identity-attestations.show', compact('attestation'))->header('Cache-Control', 'no-store, private');
    }

    public function open(Request $request, IdentityCardAttestation $attestation, IdentityAttestations $service)
    {
        abort_unless(IdentityAttestations::enabled(), 404);
        $service->open($request->user(), $attestation);

        return redirect()->route('simulation.identity-attestations.show', $attestation);
    }

    public function decide(DecideIdentityAttestation $request, IdentityCardAttestation $attestation, IdentityAttestations $service)
    {
        $service->decide($request->user(), $attestation, $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true])->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route('simulation.identity-attestations.show', $attestation)->with('status', 'تمّ تسجيل قرار المصادقة بنجاح وتحديث حالة الطلب في قاعدة البيانات.');
    }
}
