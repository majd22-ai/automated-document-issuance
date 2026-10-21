<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewServiceRequest;
use App\Http\Requests\SubmitServiceRequest;
use App\Models\GovernmentService;
use App\Services\ServiceRequestSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceRequestController extends Controller
{
    public function preview(PreviewServiceRequest $request, GovernmentService $service, ServiceRequestSubmission $submission): RedirectResponse
    {
        $preview = $submission->preview($request->user(), $service, $request->validated());
        $token = (string) Str::uuid();
        $previews = array_filter($request->session()->get('request_previews', []), fn ($item) => $item['expires_at'] >= now()->timestamp);
        // Bound session growth without creating drafts or persisting unsubmitted request data.
        $previews = array_slice($previews, -9, null, true);
        $previews[$token] = $preview;
        $request->session()->put('request_previews', $previews);

        return redirect()->route('citizen.applications.preview', $token);
    }

    public function review(Request $request, string $token, ServiceRequestSubmission $submission): Response
    {
        $preview = $request->session()->get('request_previews.'.$token);
        abort_unless($preview && $preview['user_id'] === $request->user()->id, 404);
        abort_if($preview['expires_at'] < now()->timestamp, 410, 'انتهت صلاحية الملخص. أعد مراجعة البيانات.');
        $service = GovernmentService::findOrFail($preview['service_id']);
        try {
            $submission->assertCurrentPreview($request->user(), $service, $preview);
            $submission->assertEligible($request->user(), $service);
        } catch (ValidationException $exception) {
            return response()->view('citizen.applications.create', ['service' => $service, 'reasons' => array_merge(...array_values($exception->errors()))], 422)->header('Cache-Control', 'no-store, private');
        }

        return response()->view('citizen.applications.preview', compact('preview', 'service', 'token'))->header('Cache-Control', 'no-store, private');
    }

    public function submit(SubmitServiceRequest $request, GovernmentService $service, ServiceRequestSubmission $submission): RedirectResponse
    {
        $token = $request->validated('preview_token');
        $application = $submission->submit($request->user(), $service, $token, $request->session()->get('request_previews.'.$token));
        $request->session()->forget('request_previews.'.$token);

        return redirect()->route('citizen.applications.success', $application->id);
    }

    public function success(Request $request, string $id): Response
    {
        $application = $request->user()->serviceRequests()->findOrFail($id);
        Gate::authorize('view', $application);
        abort_unless($application->submitted_at, 404);

        return response()->view('citizen.applications.success', compact('application'))->header('Cache-Control', 'no-store, private');
    }
}
