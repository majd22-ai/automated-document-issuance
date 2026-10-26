<?php

namespace App\Http\Controllers;

use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Services\Tracking\ApplicationTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApplicationTrackingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:submitted,under_review,requires_correction,accepted,approved,processing,completed,rejected,cancelled'],
            'service' => ['nullable', 'integer', 'min:1'], 'submitted_from' => ['nullable', 'date_format:Y-m-d'], 'submitted_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:submitted_from'],
            'updated_from' => ['nullable', 'date_format:Y-m-d'], 'updated_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:updated_from'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $employee = $request->user()->role === 'employee';
        $ids = $employee ? $request->user()->reviewPermissions()->where(fn ($q) => $q->where('can_review', true)->orWhere('can_approve', true)->orWhere('can_assign', true)->orWhere('can_issue', true)->orWhere('can_deliver', true))->pluck('government_service_id') : null;
        $services = GovernmentService::when($employee, fn ($q) => $q->whereIn('id', $ids))->get(['id', 'name']);
        $query = $employee ? CitizenServiceRequest::whereIn('government_service_id', $ids) : $request->user()->serviceRequests()->getQuery();
        $query->with(['issuedDocument.delivery', 'service']);
        foreach (['search' => 'reference', 'status' => 'status', 'service' => 'government_service_id'] as $key => $column) {
            if (filled($filters[$key] ?? null)) {
                $query->where($column, $filters[$key]);
            }
        }
        foreach (['submitted' => 'submitted_at', 'updated' => 'updated_at'] as $key => $column) {
            if ($filters[$key.'_from'] ?? null) {
                $query->whereDate($column, '>=', $filters[$key.'_from']);
            }
            if ($filters[$key.'_to'] ?? null) {
                $query->whereDate($column, '<=', $filters[$key.'_to']);
            }
        }
        $applications = $query->latest('id')->paginate(15)->withQueryString();

        return response()->view('tracking.index', compact('applications', 'services', 'employee'))->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, string $id)
    {
        $application = $request->user()->role === 'citizen' ? $request->user()->serviceRequests()->findOrFail($id) : CitizenServiceRequest::findOrFail($id);
        Gate::authorize('track', $application);
        $request->validate(['timeline_page' => ['sometimes', 'integer', 'min:1']]);
        $timeline = app(ApplicationTimeline::class)->rows($application);
        $nextAction = app(ApplicationTimeline::class)->nextAction($application);
        $lastDecision = $application->reviewDecisions()->whereIn('action', ['start', 'return', 'resubmit', 'accept', 'reject', 'approve', 'completed'])->latest('id')->first(['action', 'public_reason', 'decided_at']);

        return response()->view('tracking.show', compact('application', 'timeline', 'nextAction', 'lastDecision'))->header('Cache-Control', 'no-store, private');
    }
}
