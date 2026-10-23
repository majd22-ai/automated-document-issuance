<?php

namespace App\Http\Controllers;

use App\Enums\ServiceRequestStatus;
use App\Http\Requests\DocumentCommandRequest;
use App\Http\Requests\IssueDocumentRequest;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\DocumentDelivery;
use App\Services\Issuance\IssueDocuments;
use App\Services\Issuance\ManageDocuments;
use App\Services\Operations\LockedRequest;
use App\Services\Review\ReviewSnapshot;
use App\Services\ServiceEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DocumentIssuanceController extends Controller
{
    private function serviceIds(Request $request, array $abilities)
    {
        return $request->user()->reviewPermissions()->where(function ($query) use ($abilities) {
            foreach ($abilities as $ability) {
                $query->orWhere($ability, true);
            }
        })->pluck('government_service_id');
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $applications = CitizenServiceRequest::with(['citizen', 'service', 'issuedDocument'])->whereIn('government_service_id', $this->serviceIds($request, ['can_issue']))
            ->whereIn('status', ['approved', 'processing', 'completed'])->when($filters['search'] ?? null, fn ($q, $term) => $q->where('reference', $term))->latest('id')->paginate(15)->withQueryString();

        return response()->view('employee.issuance.index', compact('applications'))->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request, string $id, ServiceEligibility $eligibility)
    {
        return app(LockedRequest::class)->run($request->user(), CitizenServiceRequest::findOrFail($id), 'issueDocument', function ($actor, $owner, $service, $application) use ($eligibility) {
            auth()->setUser($actor);
            $application->setRelation('citizen', $owner);
            $application->setRelation('service', $service);
            $report = $eligibility->evaluate($owner, $service, $application, ServiceRequestStatus::Processing);
            $fingerprint = app(ReviewSnapshot::class)->fingerprint($application);
            $previous = $owner->documents()->where('document_type_code', $service->document_type)->whereDoesntHave('successor')->orderByDesc('issued_at')->get();

            return response(view('employee.issuance.review', compact('application', 'report', 'fingerprint', 'previous'))->render())->header('Cache-Control', 'no-store, private');
        });
    }

    public function issue(IssueDocumentRequest $request, string $id, IssueDocuments $issuance)
    {
        $document = $issuance->issue($request->user(), CitizenServiceRequest::findOrFail($id), $request->validated());

        return redirect()->route('employee.documents.show', $document->id)->with('status', 'تم إصدار سجل داخلي للوثيقة وربطه بالطلب. لا يمثل اعتمادًا حكوميًا.');
    }

    private function document(Request $request, string $id): CitizenDocument
    {
        $document = $request->user()->role === 'citizen' ? $request->user()->documents()->findOrFail($id) : CitizenDocument::findOrFail($id);
        Gate::authorize('view', $document);

        return $document;
    }

    public function show(Request $request, string $id)
    {
        $document = $this->document($request, $id);
        $document->load(['delivery', 'previousDocument', 'successor', 'events', 'application', 'issuer']);

        return response()->view('documents.show', compact('document'))->header('Cache-Control', 'no-store, private');
    }

    public function download(Request $request, string $id)
    {
        $document = $this->document($request, $id);
        Gate::authorize('download', $document);
        $bytes = $document->copies()->latest('id')->first()?->contents();
        if ($bytes === null) {
            return response('النسخة الإلكترونية مفقودة أو غير سليمة؛ اطلب إعادة إنشائها من الموظف المختص.', 409, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response($bytes, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="internal-document-'.$document->id.'.html"', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'", 'Referrer-Policy' => 'no-referrer']);
    }

    public function deliveries(Request $request)
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(DocumentDelivery::LABELS))], 'page' => ['sometimes', 'integer', 'min:1']]);
        $documents = CitizenDocument::with(['citizen', 'delivery'])->whereIn('government_service_id', $this->serviceIds($request, ['can_deliver']))->whereNotNull('issued_by')
            ->when($filters['status'] ?? null, fn ($q, $state) => $q->whereHas('delivery', fn ($d) => $d->where('status', $state)))->latest('id')->paginate(15)->withQueryString();

        return response()->view('employee.deliveries.index', compact('documents'))->header('Cache-Control', 'no-store, private');
    }

    public function choose(DocumentCommandRequest $request, string $id, ManageDocuments $documents)
    {
        $document = $this->document($request, $id);
        $documents->choose($request->user(), $document, $request->validated());

        return back()->with('status', 'حُفظت طريقة الاستلام. التوصيل يسجل داخليًا ولا يوجد تتبع من شركة خارجية.');
    }

    public function status(DocumentCommandRequest $request, string $id, ManageDocuments $documents)
    {
        $document = $this->document($request, $id);
        $documents->status($request->user(), $document, $request->validated());

        return back()->with('status', 'تم تسجيل حالة التسليم والتحقق من شروط إكمال الطلب.');
    }

    public function regenerate(DocumentCommandRequest $request,string $id,ManageDocuments $documents)
    {
        $document = $this->document($request,$id);
        $documents->regenerate($request->user(),$document,$request->validated());

        return back()->with('status','تم إنشاء نسخة إلكترونية جديدة مع حفظ تاريخ النسخ السابقة.');
    }
}
