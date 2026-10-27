<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CitizenNotificationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['read' => ['nullable', 'in:read,unread'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $notifications = $request->user()->notifications()->when(($filters['read'] ?? null) === 'read', fn ($q) => $q->whereNotNull('read_at'))->when(($filters['read'] ?? null) === 'unread', fn ($q) => $q->whereNull('read_at'))->latest('created_at')->latest('id')->paginate(20)->withQueryString();
        $unread = $request->user()->unreadNotifications()->count();

        return response()->view('citizen.notifications.index', compact('notifications', 'unread'))->header('Cache-Control', 'no-store, private');
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

    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        if (in_array($notification->data['kind'] ?? '', ['employee_account', 'employee_permission'], true) && ! $notification->request_id && ! $notification->document_id) {
            abort_unless((int) ($notification->data['target_user_id'] ?? $request->user()->id) === (int) $request->user()->id, 403);
            $notification->markAsRead();

            return redirect()->route('profile.edit');
        }
        if (($notification->data['kind'] ?? '') === 'attachment_update' && ! $notification->request_id && ! $notification->document_id) {
            $attachment = $request->user()->attachments()->findOrFail($notification->data['attachment_id'] ?? 0);
            Gate::authorize('view', $attachment);
            $notification->markAsRead();

            return redirect()->route('citizen.services.attachments.index', $attachment->government_service_id);
        }
        if (($notification->data['kind'] ?? null) === 'account_status' && ! $notification->request_id && ! $notification->document_id) {
            $notification->markAsRead();

            return redirect()->route('citizen.account.status');
        }
        $application = $notification->request_id ? $request->user()->serviceRequests()->findOrFail($notification->request_id) : null;
        $document = $notification->document_id ? $request->user()->documents()->findOrFail($notification->document_id) : null;
        if ($application) {
            Gate::authorize('track', $application);
        }
        if ($document) {
            Gate::authorize('view', $document);
            abort_if($application && $document->citizen_service_request_id !== $application->id, 404);
        }
        abort_unless($document || $application, 404);
        $notification->markAsRead();

        return $document ? redirect()->route('citizen.documents.show', $document->id) : redirect()->route('citizen.applications.show', $application->id);
    }
}
