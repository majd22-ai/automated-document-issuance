<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmCitizenAccountRequest;
use App\Models\User;
use App\Services\Registration\CitizenAccountActivation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CitizenAccountController extends Controller
{
    public function status(Request $request)
    {
        return response()->view('citizen.account-status', ['citizen' => $request->user()])->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request)
    {
        Gate::authorize('manageCitizenActivation', User::class);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'in:initial_account,identity_verification_pending,active,review_required'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $citizens = User::where('role', 'citizen')->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('username', $search)->orWhere('national_id', $search)->orWhere('name', $search)))
            ->when($data['state'] ?? null, fn ($q, $state) => $q->where('registration_status', $state))->orderBy('id')->paginate(15)->withQueryString();

        return response()->view('employee.accounts.index', compact('citizens', 'data'))->header('Cache-Control', 'no-store, private');
    }

    public function show(User $citizen)
    {
        Gate::authorize('activateCitizen', $citizen);
        $requests = $citizen->serviceRequests()->where('service_code', 'identity-first-issue')->where('status', 'completed')->latest('id')->paginate(15, ['*'], 'requests_page');
        $confirmations = $citizen->accountConfirmations()->with('actor')->latest('id')->paginate(15);

        return response()->view('employee.accounts.show', compact('citizen', 'requests', 'confirmations') + ['operationToken' => (string) Str::uuid()])->header('Cache-Control', 'no-store, private');
    }

    public function confirm(ConfirmCitizenAccountRequest $request, User $citizen, CitizenAccountActivation $activation)
    {
        $activation->confirm($request->user(), $citizen, $request->validated());

        return redirect()->route('employee.accounts.show', $citizen)->with('status', 'تم حفظ الاعتماد على الحساب نفسه. صلاحيات الخدمات تتبع التفعيل وتوثيق الهوية وشروط كل خدمة.');
    }
}
