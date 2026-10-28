<?php

namespace App\Http\Controllers;

use App\Http\Requests\IdentityWorkflowAction;
use App\Models\IdentityCardForm;
use App\Services\FingerprintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class IdentityWorkflowController extends Controller
{
    public function reception(Request $request, FingerprintService $service)
    {
        abort_unless(FingerprintService::enabled(), 404);
        $input = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);
        $query = $service->scopedForms($request->user());
        $form = empty($input['reference']) ? null : (clone $query)->where(fn ($q) => $q->where('reference', $input['reference'])->orWhereHas('application', fn ($application) => $application->where('reference', $input['reference'])))->first();
        $forms = $query->with(['application', 'citizen'])->latest('id')->paginate(15)->withQueryString();
        $reasons = $form ? $service->prerequisiteReasons($form) : [];
        $fingerprintReasons = $form ? $service->prerequisiteReasons($form, true) : [];
        $canRecord = $form && $request->user()->can('recordFingerprint', $form);

        return response()->view('identity-workflow.reception', compact('forms', 'form', 'reasons', 'fingerprintReasons', 'canRecord'))->header('Cache-Control', 'no-store, private');
    }

    public function arrival(IdentityWorkflowAction $request, IdentityCardForm $form, FingerprintService $service)
    {
        $service->arrive($request->user(), $form, $request->validated());

        return redirect()->route('identity-workflow.device', $form)->with('status', 'تم تسجيل الحضور وفحص الوثائق إداريًا؛ لم تُسجل بصمة حيوية.');
    }

    public function security(IdentityWorkflowAction $request, IdentityCardForm $form, FingerprintService $service)
    {
        $service->securityPaper($request->user(), $form, $request->validated());

        return redirect()->route('identity-workflow.reception', ['reference' => $form->application->reference])->with('status', 'تم حفظ مرجع الإجراء الورقي بواسطة الموظف؛ ليس ختمًا أو تحققًا آليًا.');
    }

    public function device(Request $request, IdentityCardForm $form, FingerprintService $service)
    {
        Gate::authorize('recordFingerprint', $form);
        abort_unless($form->submitted_at, 404);
        $reasons = $service->prerequisiteReasons($form, true);
        $operationToken = (string) Str::uuid();
        $operations = $form->operations()->with('operator')->get();
        $latestSuccess = $service->successfulOperation($form);

        return response()->view('identity-workflow.device', compact('form', 'reasons', 'operationToken', 'operations', 'latestSuccess'))->header('Cache-Control', 'no-store, private');
    }

    public function capture(IdentityWorkflowAction $request, IdentityCardForm $form, FingerprintService $service)
    {
        $operation = $service->capture($request->user(), $form, $request->validated());

        return response()->json(['reference' => $operation->reference, 'result' => $operation->result, 'result_label' => $operation->resultLabel(), 'source_label' => $operation->sourceLabel(), 'executed_at' => $operation->executed_at->toIso8601String(), 'operator' => $operation->operator->name, 'redirect_url' => route('identity-workflow.device', $form, false)])->header('Cache-Control', 'no-store, private');
    }

    public function manual(IdentityWorkflowAction $request, IdentityCardForm $form, FingerprintService $service)
    {
        $service->capture($request->user(), $form, $request->validated(), true);

        return redirect()->route('identity-workflow.device', $form)->with('status', 'تم تسجيل نتيجة أدخلها الموظف يدويًا؛ لم ترد من تكامل آلي موثوق.');
    }

    public function administrationIndex(Request $request, FingerprintService $service)
    {
        $forms = $service->scopedForms($request->user(), 'can_approve')->whereIn('status', ['pending_administrative_review', 'returned_to_staff', 'demo_completed', 'rejected', 'awaiting_data'])->with('application')->latest('id')->paginate(20);

        return response()->view('identity-workflow.administration-index', compact('forms'))->header('Cache-Control', 'no-store, private');
    }

    public function administrationShow(Request $request, IdentityCardForm $form, FingerprintService $service)
    {
        Gate::authorize('administer', $form);
        abort_unless($form->submitted_at, 404);
        $reasons = $service->administrationReasons($form);
        $form->load(['operations.operator', 'attestation.decider', 'witnesses', 'photo', 'application', 'administrativeDecisions.actor']);
        $decisions = $form->administrativeDecisions;

        return response()->view('identity-workflow.administration-show', compact('form', 'reasons', 'decisions'))->header('Cache-Control', 'no-store, private');
    }

    public function administrationDecide(IdentityWorkflowAction $request, IdentityCardForm $form, FingerprintService $service)
    {
        $service->administer($request->user(), $form, $request->validated());

        return redirect()->route('identity-workflow.administration.show', $form)->with('status', 'تم حفظ قرار المرحلة التجريبية وإشعار المواطن دون إصدار بطاقة حكومية.');
    }

    public function requeue(IdentityWorkflowAction $request, IdentityCardForm $form, FingerprintService $service)
    {
        $service->requeue($request->user(), $form, $request->validated());

        return redirect()->route('identity-workflow.reception', ['reference' => $form->application->reference])->with('status', 'أُعيد الطلب إلى قائمة المراجعة الإدارية.');
    }

    public function print(Request $request, IdentityCardForm $form, FingerprintService $service)
    {
        Gate::authorize('viewPrintable', $form);
        $form->load(['photo', 'witnesses', 'attestation.decider', 'application', 'receiver']);
        $latestSuccess = $service->successfulOperation($form);
        $reasons = $service->administrationReasons($form);
        $lastApproval = $form->administrativeDecisions()->where('form_revision', $form->revision)->where('action', 'approved')->with('actor')->first();
        $ready = $form->status === 'demo_completed' && $lastApproval && $latestSuccess && ! $reasons;

        return response()->view('identity-workflow.print', compact('form', 'latestSuccess', 'reasons', 'lastApproval', 'ready'))->header('Cache-Control', 'no-store, private');
    }
}
