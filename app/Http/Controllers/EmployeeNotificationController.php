<?php

namespace App\Http\Controllers;

use App\Models\CitizenServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeNotificationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['read' => ['nullable', 'in:read,unread'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $notifications = $request->user()->notifications()
            ->when(($filters['read'] ?? '') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when(($filters['read'] ?? '') === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->latest('created_at')->latest('id')->paginate(20)->withQueryString();
        $unread = $request->user()->unreadNotifications()->count();

        return response()->view('employee.notifications', compact('notifications', 'unread'))->header('Cache-Control', 'no-store, private');
    }

    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $kind = $notification->data['kind'] ?? null;
        if (in_array($kind, ['employee_account', 'employee_permission'], true)) {
            abort_unless((int) ($notification->data['target_user_id'] ?? $request->user()->id) === (int) $request->user()->id, 403);
            $notification->markAsRead();

            return redirect()->route('profile.edit');
        }
        if ($notification->request_id) {
            $application = CitizenServiceRequest::findOrFail($notification->request_id);
            Gate::authorize('track', $application);
            $notification->markAsRead();

            return redirect()->route('employee.tracking.show', $application->id);
        }
        abort_unless(in_array($kind, ['activation_request', 'activation_permission'], true), 404);
        Gate::authorize('manageCitizenActivation', User::class);
        if ($kind === 'activation_request') {
            $citizen = User::findOrFail($notification->data['citizen_id'] ?? 0);
            Gate::authorize('activateCitizen', $citizen);
            $notification->markAsRead();

            return redirect()->route('employee.accounts.show', $citizen);
        }
        $notification->markAsRead();

        return redirect()->route('employee.accounts.index', ['submitted' => 1]);
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return back()->with('status', 'تم تعليم الإشعار كمقروء.');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'تم تعليم إشعاراتك الحالية كمقروءة.');
    }
}
