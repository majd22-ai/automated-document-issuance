<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\ChangeAppointmentRequest;
use App\Http\Requests\SaveAppointmentSlotRequest;
use App\Models\AppointmentSlot;
use App\Models\CitizenAppointment;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Operations\AppointmentBookings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $employee = $request->user()->role === 'employee';
        if ($employee) {
            Gate::authorize('searchCitizens', User::class);
        }
        $appointments = CitizenAppointment::with(['slot', 'application', 'citizen'])->when(! $employee, fn ($q) => $q->where('user_id', $request->user()->id))->latest('id')->paginate(15);

        return response()->view('operations.appointments', compact('appointments', 'employee'))->header('Cache-Control', 'no-store, private');
    }

    public function book(BookAppointmentRequest $request, string $id, AppointmentBookings $bookings)
    {
        $application = $request->user()->role === 'citizen' ? $request->user()->serviceRequests()->findOrFail($id) : CitizenServiceRequest::findOrFail($id);
        $bookings->book($request->user(), $application, (int) $request->validated('slot_id'));

        return back()->with('status', 'تم حجز الموعد وربطه بالطلب.');
    }

    public function change(ChangeAppointmentRequest $request, CitizenAppointment $appointment, AppointmentBookings $bookings)
    {
        $bookings->change($request->user(), $appointment, $request->validated('action'), $request->validated('slot_id') ? (int) $request->validated('slot_id') : null);

        return back()->with('status', 'تم تحديث الموعد دون تغيير حالة الطلب.');
    }

    public function slots(Request $request)
    {
        Gate::authorize('manage', AppointmentSlot::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $slots = AppointmentSlot::with('service')->withCount(['bookings' => fn ($q) => $q->whereIn('status', ['booked', 'confirmed', 'completed', 'no_show'])])->latest('starts_at')->paginate(15);
        $services = GovernmentService::where('is_active', true)->where('rules_approved', true)->get();

        return response()->view('operations.slots', compact('slots', 'services'))->header('Cache-Control', 'no-store, private');
    }

    public function saveSlot(SaveAppointmentSlotRequest $request, ?AppointmentSlot $slot = null)
    {
        $slot = $slot?->exists ? $slot : null;
        DB::transaction(function () use ($request, $slot) {
            $service = GovernmentService::whereKey($slot?->government_service_id ?? $request->validated('government_service_id'))->lockForUpdate()->firstOrFail();
            abort_unless($request->user()->fresh()?->can('manage', AppointmentSlot::class), 403);
            if ($slot) {
                $slot = AppointmentSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
                $active = $slot->bookings()->whereIn('status', ['booked', 'confirmed'])->count();
                if ((int) $request->validated('capacity') < $slot->occupancy() || (! $request->boolean('is_active', true) && $active)) {
                    throw ValidationException::withMessages(['capacity' => 'لا يمكن تقليل السعة دون الحجوزات أو إلغاء فترة لها حجوزات قائمة.']);
                }
                $slot->forceFill(['capacity' => $request->validated('capacity'), 'is_active' => $request->boolean('is_active', true)])->save();
            } else {
                abort_unless($service->is_active && $service->rules_approved, 422);
                $start = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $request->validated('starts_at'), config('operations.timezone'))->utc();
                $end = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $request->validated('ends_at'), config('operations.timezone'))->utc();
                if ($start->lte(now()) || $end->lte($start)) {
                    throw ValidationException::withMessages(['starts_at' => 'يجب أن تبدأ الفترة في المستقبل وأن يكون انتهاؤها بعد بدايتها.']);
                }
                (new AppointmentSlot)->forceFill(['government_service_id' => $service->id, 'center' => $request->validated('center'), 'starts_at' => $start, 'ends_at' => $end, 'capacity' => $request->validated('capacity'), 'is_active' => true, 'created_by' => $request->user()->id])->save();
            }
        });

        return redirect()->route('employee.appointment-slots.index')->with('status', 'تم حفظ فترة الموعد.');
    }
}
