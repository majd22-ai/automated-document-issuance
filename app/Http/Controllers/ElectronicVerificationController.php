<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartVerificationRequest;
use App\Models\CitizenServiceRequest;
use App\Models\IntegrationAttempt;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\IntegrationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ElectronicVerificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $services = $request->user()->reviewPermissions()->where('can_verify', true)->pluck('government_service_id');
        $applications = CitizenServiceRequest::whereIn('government_service_id', $services)->latest('id')->paginate(20);

        return response()->view('verifications.index', compact('applications'))->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, string $id, IntegrationManager $manager)
    {
        $application = $request->user()->role === 'citizen' ? $request->user()->serviceRequests()->findOrFail($id) : CitizenServiceRequest::findOrFail($id);
        Gate::authorize('viewVerification', $application);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $states = [];
        $configurationError = false;
        try {
            foreach (array_keys(IntegrationRules::KINDS) as $kind) {
                $states[] = $manager->status($application, $kind);
            }
        } catch (ValidationException) {
            $configurationError = true;
        }
        $attempts = $application->integrationAttempts()->latest('created_at')->latest('id')->paginate(20);

        return response()->view('verifications.show', compact('application', 'states', 'attempts', 'configurationError'))->header('Cache-Control', 'no-store, private');
    }

    public function start(StartVerificationRequest $request, string $id, IntegrationManager $manager)
    {
        $application = CitizenServiceRequest::findOrFail($id);
        $data = $request->validated();
        $manager->start($request->user(), $application, $data['kind'], $data['idempotency_key'], isset($data['target_document_id']) ? (int) $data['target_document_id'] : null);

        return redirect()->route('employee.verifications.show', $id)->with('status', 'حُفظت حالة محاولة التحقق. راجع النتيجة؛ الحفظ لا يعني نجاح التحقق.');
    }

    public function administration(Request $request, IntegrationManager $manager)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->is_active, 403);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $providers = [];
        foreach (config('integrations.providers', []) as $key => $settings) {
            $providers[] = ['key' => $key, 'label' => $settings['label'] ?? $key, 'configured' => $manager->configured($key)];
        }
        $attempts = IntegrationAttempt::latest('created_at')->latest('id')->paginate(20);

        return response()->view('admin.integrations', compact('providers', 'attempts'))->header('Cache-Control', 'no-store, private');
    }
}
