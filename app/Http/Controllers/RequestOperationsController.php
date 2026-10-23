<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordManualPaymentRequest;
use App\Http\Requests\RecordMedicalExaminationRequest;
use App\Models\AppointmentSlot;
use App\Models\CitizenServiceRequest;
use App\Models\RequestCharge;
use App\Models\User;
use App\Services\Operations\RecordExamination;
use App\Services\Operations\RequestCharges;
use App\Services\ServiceEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RequestOperationsController extends Controller
{
    public function show(Request $request, string $id, ServiceEligibility $eligibility)
    {
        $application = $request->user()->role === 'citizen' ? $request->user()->serviceRequests()->findOrFail($id) : CitizenServiceRequest::findOrFail($id);
        Gate::authorize('view', $application);
        $service = $application->service;
        abort_unless($service, 422);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $slots = AppointmentSlot::where('government_service_id', $service->id)->where('is_active', true)->where('starts_at', '>', now())
            ->withCount(['bookings' => fn ($q) => $q->whereIn('status', ['booked', 'confirmed', 'completed', 'no_show'])])
            ->whereRaw('(select count(*) from citizen_appointments where appointment_slot_id = appointment_slots.id and status in (?,?,?,?)) < capacity', ['booked', 'confirmed', 'completed', 'no_show'])
            ->orderBy('starts_at')->paginate(12);
        $appointments = $application->appointments()->with('slot')->latest('id')->get();
        $examinations = $application->examinations()->latest('id')->limit(20)->get();
        $charge = $application->charge()->with('payment')->first();
        $report = $eligibility->evaluate($application->citizen, $service, $application);

        return response()->view('operations.request', compact('application', 'service', 'slots', 'appointments', 'examinations', 'charge', 'report'))->header('Cache-Control', 'no-store, private');
    }

    public function payments(Request $request)
    {
        $employee = $request->user()->role === 'employee';
        if ($employee) {
            Gate::authorize('searchCitizens', User::class);
        }
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $charges = RequestCharge::with(['application', 'payment'])->when(! $employee, fn ($q) => $q->where('user_id', $request->user()->id))->latest('id')->paginate(15);

        return response()->view('operations.payments', compact('charges', 'employee'))->header('Cache-Control', 'no-store, private');
    }

    public function charge(Request $request, string $id, RequestCharges $charges)
    {
        $application = CitizenServiceRequest::findOrFail($id);
        Gate::authorize('recordOperations', $application);
        $charges->ensure($application);

        return back()->with('status', 'تم التحقق من الرسم التاريخي للطلب؛ لا يُفترض مبلغ إذا كان غير مسجل.');
    }

    public function payment(RecordManualPaymentRequest $request, string $id, RequestCharges $charges)
    {
        $charges->record($request->user(), CitizenServiceRequest::findOrFail($id), $request->validated());

        return back()->with('status', 'تم تسجيل السداد اليدوي الموثق؛ لا يوجد تأكيد بنكي إلكتروني ضمن هذه العملية.');
    }

    public function examination(RecordMedicalExaminationRequest $request, string $id, RecordExamination $examinations)
    {
        $examinations->record($request->user(), CitizenServiceRequest::findOrFail($id), $request->validated());

        return back()->with('status', 'تم حفظ نسخة جديدة من نتيجة الفحص اليدوية مع الاحتفاظ بالسجل السابق.');
    }
}
