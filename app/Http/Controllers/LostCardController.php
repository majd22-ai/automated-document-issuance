<?php

namespace App\Http\Controllers;

use App\Models\LostCardCase;
use App\Services\LostCardWorkflow;
use App\Services\ServiceRequestSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LostCardController extends Controller
{
    public function index(Request $request, LostCardWorkflow $workflow)
    {
        abort_unless($workflow::enabled(), 404);
        $user = $request->user();
        if ($user->role !== 'citizen') {
            if (in_array($user->role, ['admin', 'employee'], true) && $workflow->canOperate($user)) {
                return redirect()->route($user->role.'.lost-cards.index');
            }

            return $this->privateView('lost-cards.access', []);
        }
        $cases = LostCardCase::where('user_id', $user->id)->latest('id')->paginate(15);

        return $this->privateView('lost-cards.citizen', ['cases' => $cases, 'cards' => $workflow->cards($user), 'case' => null]);
    }

    public function report(Request $request, LostCardWorkflow $workflow)
    {
        abort_unless($workflow::enabled(), 404);
        $case = $workflow->report($request->user(), $request->validate([
            'card' => ['required', 'string', 'max:100'],
            'lost_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'loss_place' => ['required', 'string', 'max:150'],
        ]));

        return redirect()->route('citizen.lost-cards.show', $case)->with('status', 'تم تسجيل طلب الإبلاغ.');
    }

    public function show(Request $request, LostCardCase $case)
    {
        $this->owner($request, $case);

        return $this->privateView('lost-cards.citizen', ['case' => $case->load('police', 'notice', 'application')]);
    }

    public function form(Request $request, LostCardCase $case, LostCardWorkflow $workflow)
    {
        $this->owner($request, $case);
        if ($case->application_id) {
            return redirect()->route('citizen.applications.show', $case->application_id);
        }
        abort_unless($case->ready(), 409, 'أكمل البلاغ والنشر أولًا.');
        $names = preg_split('/\s+/u', trim($case->holder_name), -1, PREG_SPLIT_NO_EMPTY);
        $parts = count($names) === 4 ? array_combine(['given_name', 'father_name', 'grandfather_name', 'family_name'], $names) : [];

        return $this->privateView('lost-cards.form', ['case' => $case, 'parts' => $parts, 'service' => $workflow->service()]);
    }

    public function submit(Request $request, LostCardCase $case, LostCardWorkflow $workflow, ServiceRequestSubmission $submission)
    {
        $this->owner($request, $case);
        $data = $request->validate([
            'given_name' => ['required', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['required', 'string', 'max:100'],
            'family_name' => ['required', 'string', 'max:100'],
            'issuing_governorate' => ['required', 'string', 'max:150'],
            'issuing_center' => ['required', 'string', 'max:150'],
            'previous_issued_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'. $case->lost_on],
            'signature' => ['required', 'string', 'max:200'],
            'declaration' => ['accepted'],
        ]);
        unset($data['declaration']);
        $application = $workflow->submit($case, $request->user(), $data, $submission);

        return redirect()->route('citizen.applications.success', $application->id);
    }

    public function staff(Request $request, LostCardWorkflow $workflow, ?LostCardCase $case = null)
    {
        abort_unless($workflow->canOperate($request->user()), 403);
        $queue = $case ? null : LostCardCase::with('police', 'notice', 'application')->latest('id')->paginate(20);
        $case?->load('police', 'notice', 'application');

        return $this->privateView('lost-cards.staff', ['lostCardCase' => $case, 'queue' => $queue]);
    }

    public function police(Request $request, LostCardCase $case, LostCardWorkflow $workflow)
    {
        $workflow->recordPolice($case, $request->user());

        return $this->returnToCase($request, $case, 'تم حفظ بلاغ الشرطة التجريبي.');
    }

    public function notice(Request $request, LostCardCase $case, LostCardWorkflow $workflow)
    {
        $workflow->requestNotice($case, $request->user());

        return $this->returnToCase($request, $case, 'تم حفظ طلب النشر التجريبي.');
    }

    public function publish(Request $request, LostCardCase $case, LostCardWorkflow $workflow)
    {
        $data = $request->validate([
            'published_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'issue_number' => ['required', 'string', 'max:50'],
            'page_number' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        $workflow->publish($case, $request->user(), $data);

        return $this->returnToCase($request, $case, 'تم توثيق النشر التجريبي وإشعار المواطن.');
    }

    public function verify(Request $request, LostCardCase $case, string $agency, LostCardWorkflow $workflow)
    {
        if ($request->user()->role === 'citizen') {
            $this->owner($request, $case);
        } else {
            abort_unless($workflow->canOperate($request->user()), 403);
        }
        $record = $agency === 'police' ? $case->police : $case->notice;
        abort_unless($record, 404);
        app(\App\Services\Oversight\AuditTrail::class)->record('lost_card.'.$agency.'_verified', 'lost_card_case', $case->id, $request->user());

        return response()->json(['simulation' => true, 'agency' => $agency, 'case' => $case->reference,
            'record' => $record->only(['reference', 'status', 'published_on', 'issue_number', 'page_number']),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function returnToCase(Request $request, LostCardCase $case, string $message)
    {
        return redirect()->route($request->user()->role.'.lost-cards.show', ['case' => $case, 'tools' => 'lost-cards'])->with('status', $message);
    }

    private function owner(Request $request, LostCardCase $case): void
    {
        abort_unless(LostCardWorkflow::enabled() && $request->user()->id === $case->user_id, 404);
    }

    private function privateView(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
    }
}
