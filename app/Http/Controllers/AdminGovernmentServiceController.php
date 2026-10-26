<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGovernmentServiceRequest;
use App\Http\Requests\SaveServiceAssessmentRequest;
use App\Models\GovernmentService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminGovernmentServiceController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage', GovernmentService::class);

        return response()->view('admin.services.index', ['services' => GovernmentService::orderBy('id')->paginate(15)])->header('Cache-Control', 'no-store, private');
    }

    public function create(): Response
    {
        Gate::authorize('manage', GovernmentService::class);

        return response()->view('admin.services.edit', ['service' => new GovernmentService])->header('Cache-Control', 'no-store, private');
    }

    public function edit(GovernmentService $service): Response
    {
        Gate::authorize('manage', GovernmentService::class);

        return response()->view('admin.services.edit', compact('service'))->header('Cache-Control', 'no-store, private');
    }

    public function store(SaveGovernmentServiceRequest $request): RedirectResponse
    {
        GovernmentService::create($request->validated());

        return redirect()->route('admin.services.index')->with('status', 'تمت إضافة الخدمة. لا تصبح متاحة إلا بعد الاعتماد والتفعيل.');
    }

    public function update(SaveGovernmentServiceRequest $request, GovernmentService $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service) {
            GovernmentService::whereKey($service->id)->lockForUpdate()->firstOrFail()->fill($request->validated())->save();
        });

        return redirect()->route('admin.services.index')->with('status', 'تم تحديث الخدمة مع الحفاظ على الطلبات السابقة.');
    }

    public function assessment(SaveServiceAssessmentRequest $request, GovernmentService $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service) {
            $service = GovernmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ((int) $request->validated('expected_rules_version') !== $service->rules_version) {
                throw ValidationException::withMessages(['expected_rules_version' => 'تغيرت شروط الخدمة منذ فتح النموذج. حدّث الصفحة وأعد التحقق من الشروط الحالية.']);
            }
            $citizen = $request->filled('citizen_account_id')
                ? User::where('role', 'citizen')->where('registration_status', 'initial_account')->whereKey($request->validated('citizen_account_id'))->first()
                : User::where('role', 'citizen')->where(function ($query) use ($request) {
                    $query->where('national_id', $request->validated('username'))->orWhere(function ($legacy) use ($request) {
                        $legacy->whereNull('registration_status')->where('username', $request->validated('username'));
                    });
                })->first();
            if ($request->filled('citizen_account_id') && $service->code !== 'identity-first-issue') {
                throw ValidationException::withMessages(['citizen_account_id' => 'تقييم الحساب الأولي بمعرّفه الداخلي متاح لإصدار البطاقة لأول مرة فقط.']);
            }
            if (! $citizen) {
                throw ValidationException::withMessages(['username' => 'لا يوجد حساب مواطن مطابق لهذا الرقم الوطني.']);
            }
            $data = $request->safe()->except(['username', 'citizen_account_id', 'expected_rules_version']);
            $data['age_requirement_met'] = $request->boolean('age_requirement_met');
            $data['driving_requirements_met'] = $request->boolean('driving_requirements_met');
            $data['rules_version'] = $service->rules_version;
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
            $service->assessments()->updateOrCreate(['user_id' => $citizen->id], $data);
        });

        return redirect()->route('admin.services.edit', $service)->with('status', 'تم حفظ التقييم الموثق؛ سيعاد فحص الأهلية عند بدء الخدمة.');
    }
}
