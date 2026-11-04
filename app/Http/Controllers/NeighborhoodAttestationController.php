<?php

namespace App\Http\Controllers;

use App\Models\IdentityCardAttestation;
use App\Models\NeighborhoodOfficial;
use App\Services\IdentityAttestations;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NeighborhoodAttestationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(IdentityAttestations::enabled() && NeighborhoodOfficial::authorized($request->user()), 403);
        $attestations = IdentityCardAttestation::whereHas('official', fn ($q) => $q->where('user_id', $request->user()->id)->where('is_active', true)->where('can_attest', true))
            ->whereHas('form', fn ($q) => $q->where('user_id', '!=', $request->user()->id))->latest('id')->paginate(20);

        return response()->view('employee.neighborhood-attestations', compact('attestations'))->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, IdentityCardAttestation $attestation, IdentityAttestations $service)
    {
        abort_unless($service->canAttest($request->user()->fresh(), $attestation), 403);

        return response()->view('employee.neighborhood-attestation', compact('attestation'))->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request, IdentityCardAttestation $attestation, IdentityAttestations $service)
    {
        abort_unless($service->canAttest($request->user()->fresh(), $attestation) && $attestation->status === 'pending', 403);
        $request->validate(['simulation_acknowledged' => ['accepted'], 'method' => ['required', 'in:simulated_fingerprint,simulated_device_confirmation']]);
        $token = Str::random(64);
        $request->session()->put('neighborhood_confirmation.'.$attestation->id, ['actor' => $request->user()->id,
            'revision' => $attestation->form->revision, 'expires' => time() + 180, 'token_hash' => hash('sha256', $token)]);
        app(AuditTrail::class)->record('neighborhood_local_confirmation', IdentityCardAttestation::class, $attestation->id, $request->user(), changes: ['action' => $request->input('method'), 'status' => 'confirmed']);

        return back()->with('confirmation_token', $token)->with('status', 'نجح التأكيد التجريبي فقط؛ لم يُستخدم مستشعر الهاتف أو رمز قفله. أكّد تسجيل الموافقة خلال ثلاث دقائق.');
    }

    public function decide(Request $request, IdentityCardAttestation $attestation, IdentityAttestations $service)
    {
        abort_unless($service->canAttest($request->user()->fresh(), $attestation), 403);
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'reason' => ['nullable', 'required_if:decision,rejected', 'string', 'max:1500'], 'confirmation_token' => ['nullable', 'string', 'max:128']]);
        $service->decide($request->user(), $attestation, $data);

        return back()->with('status', 'تم تسجيل القرار وإشعار المواطن. هذا اعتماد داخلي تجريبي وليس ختمًا حكوميًا.');
    }
}
