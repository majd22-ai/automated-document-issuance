<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchCitizensRequest;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\ServiceEligibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CitizenDataController extends Controller
{
    private function page(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request): Response
    {
        $citizen = $request->user();
        Gate::authorize('viewCitizenData', $citizen);

        return $this->page('citizen.data', ['citizen' => $citizen, 'missing' => $citizen->missingCitizenFields()]);
    }

    public function documents(Request $request): Response
    {
        Gate::authorize('viewCitizenData', $request->user());

        return $this->page('citizen.documents.index', ['documents' => $request->user()->documents()->latest('id')->paginate(15)]);
    }

    public function applications(Request $request): Response
    {
        Gate::authorize('viewCitizenData', $request->user());

        $applications = $this->filterApplications($request, $request->user()->serviceRequests()->getQuery());

        return $this->page('citizen.applications.index', compact('applications'));
    }

    public function application(Request $request, string $id): Response
    {
        Gate::authorize('viewCitizenData', $request->user());
        $application = $request->user()->serviceRequests()->findOrFail($id);
        Gate::authorize('view', $application);

        return $this->page('citizen.applications.show', compact('application'));
    }

    private function filterApplications(Request $request, Builder $query)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);

        return $query->when(filled($filters['search'] ?? null), fn ($q) => $q->where('reference', $filters['search']))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->latest('id')->paginate(15)->withQueryString();
    }

    public function employeeApplications(Request $request): Response
    {
        Gate::authorize('searchCitizens', User::class);
        $applications = $this->filterApplications($request, CitizenServiceRequest::query()->whereHas('citizen', fn ($q) => $q->where('role', 'citizen')));

        return $this->page('employee.applications.index', compact('applications'));
    }

    public function employeeApplication(string $id): Response
    {
        $application = CitizenServiceRequest::findOrFail($id);
        Gate::authorize('view', $application);

        return $this->page('employee.reviews.show', compact('application'));
    }

    public function recheckRequirements(Request $request, string $id, ServiceEligibility $eligibility): Response
    {
        $application = $request->user()->role === 'citizen'
            ? $request->user()->serviceRequests()->findOrFail($id) : CitizenServiceRequest::findOrFail($id);
        Gate::authorize('view', $application);
        $service = $application->service;
        abort_unless($service, 422, 'السجل السابق لا يرتبط بخدمة يمكن التحقق من قواعدها.');
        $application = DB::transaction(function () use ($application, $request, $eligibility) {
            // Match submission and transition lock ordering to serialize reports with rule changes.
            $citizen = User::query()->lockForUpdate()->findOrFail($application->user_id);
            $service = GovernmentService::query()->lockForUpdate()->findOrFail($application->government_service_id);
            $locked = CitizenServiceRequest::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($locked->user_id === $citizen->id && $locked->government_service_id === $service->id, 409);
            $actor = $request->user()->id === $citizen->id ? $citizen : $request->user()->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('view', $locked);
            $report = $eligibility->evaluate($citizen, $service, $locked);
            $eligibility->audit($report, $citizen, $service, $locked);
            $locked->forceFill(['requirements_result' => $report->toArray(), 'requirements_checked_at' => now()])->save();

            return $locked;
        });

        return $this->page($request->user()->role === 'citizen' ? 'citizen.applications.show' : 'employee.reviews.show', compact('application'));
    }

    public function search(SearchCitizensRequest $request): Response
    {
        $search = trim($request->validated('search') ?? '');
        $citizens = null;
        if ($search !== '') {
            // Escape LIKE wildcards using a portable escape character, not raw SQL input.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $citizens = User::query()->select(['id', 'name', 'username'])
                ->where('role', 'citizen')->where(function ($query) use ($pattern, $search) {
                    $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhere('username', $search);
                })->orderBy('id')->paginate(15)->withQueryString();
        }

        return $this->page('employee.citizens.index', compact('search', 'citizens'));
    }

    public function employeeShow(User $citizen): Response
    {
        Gate::authorize('viewCitizenData', $citizen);

        return $this->page('employee.citizens.show', [
            'citizen' => $citizen, 'missing' => $citizen->missingCitizenFields(),
            'documents' => $citizen->documents()->latest('id')->paginate(15, ['*'], 'documents_page'),
            'applications' => $citizen->serviceRequests()->latest('id')->paginate(15, ['*'], 'applications_page'),
        ]);
    }
}
