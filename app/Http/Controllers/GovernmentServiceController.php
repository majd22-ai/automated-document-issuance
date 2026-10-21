<?php

namespace App\Http\Controllers;

use App\Models\GovernmentService;
use App\Services\ServiceEligibility;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class GovernmentServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->route('documentType');
        $services = GovernmentService::where('is_active', true)->when($type, fn ($query) => $query->where('document_type', $type))->orderBy('id')->paginate(12);
        $title = $type ? 'خدمات '.GovernmentService::DOCUMENT_TYPES[$type] : 'الخدمات الحكومية';

        return response()->view('public.services.index', compact('services', 'title'));
    }

    public function legacyDetail(Request $request, ServiceEligibility $eligibility): Response
    {
        $prefix = ['identity' => 'identity', 'passport' => 'passport', 'driving_license' => 'license'][$request->route('documentType')];
        $suffix = ['first-issue' => 'first-issue', 'renewal' => 'renewal', 'lost-replacement' => 'lost'][$request->route('type')] ?? null;
        abort_unless($suffix, 404);

        return $this->show($request, GovernmentService::resolveCode($prefix.'-'.$suffix), $eligibility);
    }

    public function show(Request $request, GovernmentService $service, ServiceEligibility $eligibility): Response
    {
        $report = $request->user()?->role === 'citizen' ? $eligibility->evaluate($request->user(), $service) : null;
        $reasons = $report?->reasons();

        return response()->view('public.services.show', compact('service', 'reasons', 'report'))->header('Cache-Control', 'no-store, private');
    }

    public function entry(Request $request, ServiceEligibility $eligibility): Response
    {
        $data = Validator::make($request->query(), ['service' => ['nullable', 'string', 'max:100']])->validate();
        if (empty($data['service'])) {
            $services = GovernmentService::where('is_active', true)->orderBy('id')->paginate(12);

            return response()->view('public.services.index', ['services' => $services, 'title' => 'اختر الخدمة الحكومية'])->header('Cache-Control', 'no-store, private');
        }

        return $this->start($request, GovernmentService::resolveCode($data['service']), $eligibility);
    }

    public function start(Request $request, GovernmentService $service, ServiceEligibility $eligibility): Response
    {
        $report = $eligibility->evaluate($request->user(), $service);
        $reasons = $report->reasons();

        return response()->view('citizen.applications.create', compact('service', 'reasons', 'report'), $reasons ? 422 : 200)->header('Cache-Control', 'no-store, private');
    }
}
