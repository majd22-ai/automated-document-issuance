<?php

namespace App\Http\Controllers;

use App\Enums\ServiceRequestStatus;
use App\Http\Requests\AssignReviewRequest;
use App\Http\Requests\ResolveReviewDeficiencyRequest;
use App\Http\Requests\ResubmitReviewedRequest;
use App\Http\Requests\ReviewDecisionRequest;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Operations\LockedRequest;
use App\Services\Review\ReviewAdministration;
use App\Services\Review\ReviewSnapshot;
use App\Services\ServiceEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApplicationReviewController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('searchCitizens', User::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'service' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:submitted,under_review,requires_correction,accepted,approved,rejected,processing,completed,cancelled'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'assigned_to' => ['nullable', 'integer', 'min:1'], 'mine' => ['sometimes', 'boolean'], 'requirements' => ['nullable', 'in:ready,missing,unknown'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $serviceIds = $request->user()->reviewPermissions()->where(fn ($q) => $q->where('can_review', true)->orWhere('can_approve', true)->orWhere('can_assign', true))->pluck('government_service_id');
        $services = GovernmentService::whereIn('id', $serviceIds)->get(['id', 'name']);
        $applications = CitizenServiceRequest::with(['citizen', 'assignee', 'charge'])->whereIn('government_service_id', $serviceIds)->whereHas('citizen', fn ($q) => $q->where('role', 'citizen'))
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where('reference', $filters['search']))
            ->when($filters['service'] ?? null, fn ($q, $id) => $q->where('government_service_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $state) => $q->where('status', $state))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('submitted_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('submitted_at', '<=', $date))
            ->when($filters['assigned_to'] ?? null, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when(($filters['requirements'] ?? null) === 'unknown', fn ($q) => $q->whereNull('requirements_result'))
            ->when(in_array($filters['requirements'] ?? null, ['ready', 'missing'], true), fn ($q) => $q->where('requirements_result->eligible', $filters['requirements'] === 'ready'))
            ->latest('id')->paginate(15)->withQueryString();

        return response()->view('employee.applications.index', compact('applications', 'services'))->header('Cache-Control', 'no-store, private');
    }

    public function show(string $id, ServiceEligibility $eligibility)
    {
        $initial = CitizenServiceRequest::findOrFail($id);

        return app(LockedRequest::class)->run(auth()->user(), $initial, 'viewReview', function ($actor, $owner, $service, $application) use ($eligibility) {
            auth()->setUser($actor);
            $application->load(['citizen', 'assignee', 'deficiencies', 'reviewDecisions.actor']);
            abort_unless($application->service, 422);
            $report = $eligibility->evaluate($application->citizen, $application->service, $application, ServiceRequestStatus::Accepted);
            $approvalReport = $eligibility->evaluate($application->citizen, $application->service, $application, ServiceRequestStatus::Processing);
            $fingerprint = app(ReviewSnapshot::class)->fingerprint($application);
            $reviewers = User::where('role', 'employee')->where('is_active', true)->whereHas('reviewPermissions', fn ($q) => $q->where('government_service_id', $application->government_service_id)->where('can_review', true))->get(['id', 'name']);

            return response(view('employee.reviews.show', compact('application', 'report', 'approvalReport', 'fingerprint', 'reviewers'))->render())->header('Cache-Control', 'no-store, private');
        });
    }

    public function decide(ReviewDecisionRequest $request, string $id)
    {
        $application = CitizenServiceRequest::findOrFail($id);
        $next = match ($request->validated('action')) {
            'start' => ServiceRequestStatus::UnderReview,'accept' => ServiceRequestStatus::Accepted,'return' => ServiceRequestStatus::RequiresCorrection,'reject' => ServiceRequestStatus::Rejected,'approve' => ServiceRequestStatus::Approved
        };
        $application->transitionTo($next, $request->user(), $request->validated('public_reason'), $request->validated());

        return redirect()->route('employee.applications.review', $id)->with('status', 'تم تسجيل القرار وتحديث الطلب دون إصدار وثيقة.');
    }

    public function assign(AssignReviewRequest $request, string $id, ReviewAdministration $reviews)
    {
        $reviews->assign($request->user(), CitizenServiceRequest::findOrFail($id), $request->validated());

        return redirect()->route('employee.applications.review', $id)->with('status', 'تم تسجيل إسناد الطلب.');
    }

    public function resolve(ResolveReviewDeficiencyRequest $request, string $id, string $deficiency, ReviewAdministration $reviews)
    {
        $application = CitizenServiceRequest::findOrFail($id);
        $row = $application->deficiencies()->findOrFail($deficiency);
        $reviews->resolve($request->user(), $application, $row, $request->validated());

        return redirect()->route('employee.applications.review', $id)->with('status', 'تم توثيق التحقق من استكمال النقص.');
    }

    public function correction(Request $request, string $id)
    {
        $initial = $request->user()->serviceRequests()->findOrFail($id);

        return app(LockedRequest::class)->run($request->user(), $initial, 'view', function ($actor, $owner, $service, $application) {
            abort_unless($actor->role === 'citizen' && $actor->id === $owner->id, 403);
            auth()->setUser($actor);
            abort_unless($application->status === 'requires_correction', 403);
            $deficiencies = $application->deficiencies()->where('status', '!=', 'resolved')->get();
            $fingerprint = app(ReviewSnapshot::class)->fingerprint($application);

            return response(view('citizen.applications.correction', compact('application', 'deficiencies', 'fingerprint'))->render())->header('Cache-Control', 'no-store, private');
        });
    }

    public function resubmit(ResubmitReviewedRequest $request, string $id)
    {
        $application = $request->user()->serviceRequests()->findOrFail($id);
        $application->transitionTo(ServiceRequestStatus::Submitted, $request->user(), null, $request->validated());

        return redirect()->route('citizen.applications.show', $id)->with('status', 'أعيد تقديم الطلب. تبقى النواقص بانتظار تحقق الموظف مع حفظ سجل الإرجاع السابق.');
    }
}
