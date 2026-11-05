<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmCitizenAccountRequest;
use App\Models\CitizenAccountConfirmation;
use App\Models\User;
use App\Services\Registration\ActivationRequests;
use App\Services\Registration\CitizenAccountActivation;
use App\Services\Registration\InPersonActivation;
use App\Services\Registration\MockCivilSystems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CitizenAccountController extends Controller
{
    public function status(Request $request)
    {
        if (MockCivilSystems::enabled() && str_ends_with((string) $request->user()->activation_source, '_local_mock')) {
            return response()->view('citizen.mock-activation', ['citizen' => $request->user(), 'activationRequest' => $request->user()->activationRequest])->header('Cache-Control', 'no-store, private');
        }

        return response()->view('citizen.account-status', ['citizen' => $request->user(), 'activationRequest' => $request->user()->activationRequest])->header('Cache-Control', 'no-store, private');
    }

    public function submit(Request $request, ActivationRequests $requests)
    {
        abort(410, 'انتقل إلى صفحة تفعيل حسابي لإتمام التحقق ورفع الوثائق أولًا.');
    }

    public function index(Request $request)
    {
        Gate::authorize('manageCitizenActivation', User::class);
        $data = $request->validate(['submitted' => ['sometimes', 'boolean'], 'search' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'in:initial_account,identity_verification_pending,active,review_required,pending_activation,under_review,action_required,suspended'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $citizens = User::where('role', 'citizen')->with('activationRequest')->when($request->boolean('submitted'), fn ($q) => $q->whereHas('activationRequest', fn ($q) => $q->where('status', 'pending')))->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('username', $search)->orWhere('national_id', $search)->orWhere('name', $search)))
            ->when($data['state'] ?? null, fn ($q, $state) => $q->where('registration_status', $state))->orderBy('id')->paginate(15)->withQueryString();

        return response()->view('employee.accounts.index', compact('citizens', 'data'))->header('Cache-Control', 'no-store, private');
    }

    public function show(User $citizen)
    {
        Gate::authorize('activateCitizen', $citizen);
        $requests = $citizen->serviceRequests()->where('service_code', 'identity-first-issue')->where('status', 'completed')->latest('id')->paginate(15, ['*'], 'requests_page');
        $confirmations = $citizen->accountConfirmations()->with('actor')->latest('id')->paginate(15);

        $visit = $citizen->activationRequest;

        return response()->view('employee.accounts.show', compact('citizen', 'requests', 'confirmations', 'visit') + ['operationToken' => (string) Str::uuid()])->header('Cache-Control', 'no-store, private');
    }

    public function prepare(Request $request, User $citizen, InPersonActivation $flow)
    {
        abort(410, 'أُوقف التفعيل بالحضور. ينفذ المواطن التحقق ورفع الوثائق من حسابه.');
    }

    public function finger(Request $request, User $citizen, InPersonActivation $flow)
    {
        abort(410, 'أُزيلت محاكاة البصمة من تفعيل الحساب.');
    }

    public function verifyActivationCode(Request $request, User $citizen, InPersonActivation $flow)
    {
        abort(410, 'التحقق من الجوال أصبح من حساب المواطن.');
    }

    public function sendActivationCode(Request $request, User $citizen, InPersonActivation $flow)
    {
        abort(410, 'إرسال الرمز أصبح من حساب المواطن.');
    }

    public function confirm(ConfirmCitizenAccountRequest $request, User $citizen, CitizenAccountActivation $activation)
    {
        $replay = CitizenAccountConfirmation::where('operation_token', $request->validated('operation_token'))->exists();
        if (! $replay && $request->validated('action') === 'activate') {
            abort_unless($citizen->activationRequest?->workflow === 'remote', 409, 'أكمل طلب التفعيل الإلكتروني من حساب المواطن أولًا.');
        }
        $activation->confirm($request->user(), $citizen, $request->validated());

        return redirect()->route('employee.accounts.show', $citizen)->with('status', 'تم حفظ الاعتماد على الحساب نفسه. صلاحيات الخدمات تتبع التفعيل وتوثيق الهوية وشروط كل خدمة.');
    }
}
