<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmCitizenAccountRequest;
use App\Models\ActivationFingerprintAttempt;
use App\Models\CitizenAccountConfirmation;
use App\Models\User;
use App\Services\Registration\ActivationRequests;
use App\Services\Registration\CitizenAccountActivation;
use App\Services\Registration\InPersonActivation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CitizenAccountController extends Controller
{
    public function status(Request $request)
    {
        return response()->view('citizen.account-status', ['citizen' => $request->user(), 'activationRequest' => $request->user()->activationRequest])->header('Cache-Control', 'no-store, private');
    }

    public function submit(Request $request, ActivationRequests $requests)
    {
        $request->validate(['acknowledged' => ['required', 'accepted']], ['acknowledged.required' => 'أكد مراجعة بياناتك وتعليمات التفعيل.', 'acknowledged.accepted' => 'أكد مراجعة بياناتك وتعليمات التفعيل.']);
        $requests->submit($request->user());

        return redirect()->route('citizen.account.status')->with('status', 'تم تسجيل طلب التفعيل وإتاحته للموظفين المخوّلين. إذا سبق إرساله فلن يُكرر.');
    }

    public function index(Request $request)
    {
        Gate::authorize('manageCitizenActivation', User::class);
        $data = $request->validate(['submitted' => ['sometimes', 'boolean'], 'search' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'in:initial_account,identity_verification_pending,active,review_required'], 'page' => ['sometimes', 'integer', 'min:1']]);
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
        $flow = app(InPersonActivation::class);
        $visitReady = $visit && $visit->visit_actor_id === auth()->id() && ! $visit->visit_consumed_at
            && $visit->visit_expires_at?->isFuture() && hash_equals($visit->account_binding ?? '', $flow->binding($citizen));
        $rightPassed = $visitReady && $flow->passed($visit, 'right_index');
        $leftPassed = $visitReady && $flow->passed($visit, 'left_index');
        $fingerAttempts = $visit ? ActivationFingerprintAttempt::where('activation_request_id', $visit->id)->latest('id')->limit(20)->get() : collect();

        return response()->view('employee.accounts.show', compact('citizen', 'requests', 'confirmations', 'visit', 'visitReady', 'rightPassed', 'leftPassed', 'fingerAttempts') + ['operationToken' => (string) Str::uuid()])->header('Cache-Control', 'no-store, private');
    }

    public function prepare(Request $request, User $citizen, InPersonActivation $flow)
    {
        Gate::authorize('activateCitizen', $citizen);
        $data = $request->validate(['identity_type' => ['required', 'in:national_id,birth_certificate'],
            'identity_number' => ['required', 'string', 'regex:/^[0-9]{5,50}$/'],
            'phone' => ['required', 'string', 'regex:/^\+9677[0-9]{8}$/'], 'phone_change_confirmed' => ['sometimes', 'accepted']],
            ['*.required' => 'هذا الحقل مطلوب.', 'identity_number.regex' => 'أدخل رقم الهوية أو شهادة الميلاد من 5 إلى 50 رقمًا.',
                'phone.regex' => 'أدخل رقم جوال يمني صحيحًا: +967 ثم تسعة أرقام تبدأ بـ7.', 'phone_change_confirmed.accepted' => 'أكد تعديل الهاتف قبل حفظ البيانات.']);
        $flow->begin($request->user(), $citizen, $data);

        return back()->with('status', 'حُفظت بيانات الحضور للمراجعة. تعديل الهاتف المقترح سيطبق عند نجاح الاعتماد فقط.');
    }

    public function finger(Request $request, User $citizen, InPersonActivation $flow)
    {
        Gate::authorize('activateCitizen', $citizen);
        $data = $request->validate(['visit_token' => ['required', 'uuid'], 'operation_token' => ['required', 'uuid'],
            'finger' => ['required', 'in:right_index,left_index'], 'force_failure' => ['sometimes', 'boolean'],
            'source' => ['missing'], 'result' => ['missing'], 'biometric_data' => ['missing'], 'image' => ['missing'], 'template' => ['missing']]);
        $attempt = $flow->finger($request->user(), $citizen, $data);

        return response()->json(['result' => $attempt->result, 'source' => 'محاكاة بصمة محلية', 'time' => $attempt->performed_at->toISOString()])->header('Cache-Control', 'no-store, private');
    }

    public function sendActivationCode(Request $request, User $citizen, InPersonActivation $flow)
    {
        Gate::authorize('activateCitizen', $citizen);
        $data = $request->validate(['visit_token' => ['required', 'uuid']]);

        return response()->json($flow->send($request->user(), $citizen, $data['visit_token']))->header('Cache-Control', 'no-store, private');
    }

    public function confirm(ConfirmCitizenAccountRequest $request, User $citizen, CitizenAccountActivation $activation)
    {
        $replay = CitizenAccountConfirmation::where('operation_token', $request->validated('operation_token'))->exists();
        if (! $replay && ! $citizen->hasActivatedIdentity()) {
            app(InPersonActivation::class)->verify($request->user(), $citizen, $request->validated('visit_token'), $request->validated('otp'));
        }
        $activation->confirm($request->user(), $citizen, $request->validated());

        return redirect()->route('employee.accounts.show', $citizen)->with('status', 'تم حفظ الاعتماد على الحساب نفسه. صلاحيات الخدمات تتبع التفعيل وتوثيق الهوية وشروط كل خدمة.');
    }
}
