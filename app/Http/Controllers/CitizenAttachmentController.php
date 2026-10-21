<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewCitizenAttachmentRequest;
use App\Http\Requests\StoreCitizenAttachmentRequest;
use App\Models\CitizenAttachment;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Attachments\AttachmentRules;
use App\Services\Attachments\ManageAttachments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CitizenAttachmentController extends Controller
{
    public function index(Request $request, GovernmentService $service, AttachmentRules $rules, ManageAttachments $manager)
    {
        $manager->assertUploadContext($request->user(), $service, null);

        return $this->page($service, $request->user(), null, $rules);
    }

    public function application(Request $request, string $id, AttachmentRules $rules)
    {
        $application = $request->user()->serviceRequests()->findOrFail($id);
        Gate::authorize('view', $application);
        abort_unless($application->service, 422, 'الخدمة المرتبطة بهذا السجل غير متوفرة.');

        return $this->page($application->service, $request->user(), $application, $rules);
    }

    private function page(GovernmentService $service, User $citizen, ?CitizenServiceRequest $application, AttachmentRules $rules)
    {
        $documentRules = $rules->all($service);
        $attachments = $citizen->attachments()->where('government_service_id', $service->id)->where('citizen_service_request_id', $application?->id)->get()->keyBy('document_code');
        $editable = $service->is_active && $service->rules_approved && (! $application || $application->status === 'requires_correction');

        return response()->view('citizen.attachments.index', compact('service', 'application', 'documentRules', 'attachments', 'editable'))->header('Cache-Control', 'no-store, private');
    }

    public function store(StoreCitizenAttachmentRequest $request, GovernmentService $service, ManageAttachments $manager)
    {
        $manager->store($request->user(), $service, null, $request->validated('document_code'), $request->file('file'));

        return redirect()->route('citizen.services.attachments.index', $service)->with('status', 'تم رفع الملف والتحقق منه فنيًا. راجع حالة المراجعة الموضحة أدناه.');
    }

    public function storeForApplication(StoreCitizenAttachmentRequest $request, string $id, ManageAttachments $manager)
    {
        $application = $request->user()->serviceRequests()->findOrFail($id);
        Gate::authorize('view', $application);
        abort_unless($application->service, 422);
        $manager->store($request->user(), $application->service, $application, $request->validated('document_code'), $request->file('file'));

        return redirect()->route('citizen.applications.attachments.index', $id)->with('status', 'تم حفظ المرفق. يلزم إعادة التحقق من متطلبات الطلب.');
    }

    public function destroy(Request $request, CitizenAttachment $attachment, ManageAttachments $manager)
    {
        Gate::authorize('update', $attachment);
        $manager->delete($request->user(), $attachment);

        return back()->with('status', 'تم حذف المرفق الاختياري.');
    }

    public function preview(CitizenAttachment $attachment)
    {
        return $this->read($attachment, false);
    }

    public function download(CitizenAttachment $attachment)
    {
        return $this->read($attachment, true);
    }

    private function read(CitizenAttachment $attachment, bool $download)
    {
        Gate::authorize('view', $attachment);
        abort_unless($attachment->intact(), 404, 'الملف غير متاح أو تعذر التحقق من سلامته.');
        $stream = Storage::disk('attachments')->readStream($attachment->storage_path);
        abort_unless(is_resource($stream), 404);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$attachment->mime_type] ?? 'bin';
        // PDF is always downloaded; do not run an uploaded document inside the application's origin.
        $disposition = $download || $extension === 'pdf' ? 'attachment' : 'inline';

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $attachment->mime_type, 'Content-Length' => (string) $attachment->size,
            'Content-Disposition' => $disposition.'; filename="document-'.$attachment->id.'.'.$extension.'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function employeeIndex(Request $request)
    {
        Gate::authorize('searchCitizens', User::class);
        $filters = $request->validate(['review_status' => ['nullable', 'in:pending,uploaded,approved,rejected,replacement_required'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $attachments = CitizenAttachment::with(['citizen', 'service', 'application'])
            ->whereHas('citizen', fn ($q) => $q->where('role', 'citizen'))
            ->when($filters['review_status'] ?? null, fn ($q, $state) => $q->where('review_status', $state))->latest('id')->paginate(15)->withQueryString();

        return response()->view('employee.attachments.index', compact('attachments'))->header('Cache-Control', 'no-store, private');
    }

    public function review(ReviewCitizenAttachmentRequest $request, CitizenAttachment $attachment, ManageAttachments $manager)
    {
        $manager->review($request->user(), $attachment, $request->validated());

        return redirect()->route('employee.attachments.index')->with('status', 'تم تسجيل مراجعة المرفق فقط؛ لم تتغير حالة الطلب.');
    }
}
