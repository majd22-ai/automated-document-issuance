<?php

namespace App\Services\Issuance;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\DocumentDelivery;
use App\Models\DocumentEvent;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Operations\LockedRequest;
use App\Services\Requirements\EvidenceRequirements;
use App\Services\Requirements\PriorDocumentRequirements;
use App\Services\Review\ApprovalReadiness;
use App\Services\Review\ReviewActions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class IssueDocuments
{
    public function __construct(private LockedRequest $locks, private ElectronicCopies $copies, private DocumentAudit $audit) {}

    public function issue(User $actor, CitizenServiceRequest $request, array $data): CitizenDocument
    {
        $newPath = null;
        try {
            return $this->locks->run($actor, $request, 'issueDocument', function ($actor, $owner, $service, $request) use ($data, &$newPath) {
                $existing = $request->issuedDocument()->first();
                if ($existing && $this->audit->replay($existing, $actor, 'issued', $data)) {
                    return $existing;
                }
                if ($existing || DocumentEvent::where('operation_token', $data['operation_token'])->exists()) {
                    throw ValidationException::withMessages(['document' => 'صدرت وثيقة لهذا الطلب أو استُخدم رمز العملية مسبقًا. لا يسمح بإصدار مكرر.']);
                }
                app(ReviewActions::class)->assertCurrent($request, $data);
                app(ApprovalReadiness::class)->check($request);
                $rules = app(IssuanceRules::class)->get($service->issuance_rules);
                $previous = null;
                if ($service->operation === 'renewal') {
                    $previous = $owner->documents()->whereKey($data['previous_document_id'] ?? 0)->lockForUpdate()->first();
                    if (! $previous || $previous->document_type_code !== $service->document_type || $previous->successor()->exists()) {
                        throw ValidationException::withMessages(['previous_document_id' => 'اختر وثيقة سابقة من النوع نفسه تخص المواطن ولم يسبق تجديدها.']);
                    }
                    [$check,$candidate] = app(PriorDocumentRequirements::class)->evaluate($owner, $service, $previous);
                    if (! $candidate) {
                        throw ValidationException::withMessages(['previous_document_id' => $check->message]);
                    }
                    if ($service->verification_rules['document_authenticity_required'] ?? false) {
                        $authenticity = app(EvidenceRequirements::class)->authenticity($owner, $service, $previous, $request);
                        if ($authenticity->state !== RequirementState::Met) {
                            throw ValidationException::withMessages(['previous_document_id' => $authenticity->message]);
                        }
                    }
                } elseif ($service->operation !== 'issue' || isset($data['previous_document_id'])) {
                    throw ValidationException::withMessages(['issuance' => 'الاستبدال وإعادة الإصدار يحتاجان سياسة مستقلة غير مهيأة؛ هذه العملية تدعم الإصدار الأول والتجديد فقط.']);
                }
                $document = (new CitizenDocument)->forceFill([
                    'user_id' => $owner->id, 'citizen_service_request_id' => $request->id, 'government_service_id' => $service->id, 'previous_document_id' => $previous?->id,
                    'document_type' => GovernmentService::DOCUMENT_TYPES[$service->document_type], 'document_type_code' => $service->document_type,
                    'document_number' => app(DocumentNumbers::class)->next($rules), 'status' => 'active', 'issued_at' => today(),
                    'expires_at' => $rules['expiry']['required'] ? today()->addDays($rules['expiry']['validity_days']) : null,
                    'issued_by' => $actor->id, 'issued_recorded_at' => now(), 'issuance_rules' => $rules, 'holder_snapshot' => ['name' => $owner->name], 'workflow_version' => 0,
                ]);
                $document->save();
                (new DocumentDelivery)->forceFill(['citizen_document_id' => $document->id, 'status' => 'awaiting_choice'])->save();
                if ($rules['electronic_copy'] !== 'none') {
                    try {
                        $copy = $this->copies->generate($document, $actor);
                        $newPath = $copy->storage_path;
                    } catch (\Throwable) {
                        throw ValidationException::withMessages(['copy' => 'تعذر إنشاء النسخة الإلكترونية. لم يُصدر السجل ولم تتغير الوثيقة السابقة. أعد المحاولة بعد معالجة التخزين.']);
                    }
                }
                $this->audit->record($document, $actor, 'issued', $data, from: null, to: 'active', details: ['request_id' => $request->id, 'rules_version' => $service->rules_version, 'previous_document_id' => $previous?->id]);
                $request = $request->transitionTo(ServiceRequestStatus::Processing, $actor);
                if ($previous) {
                    $from = $previous->status;
                    $previous->forceFill(['status' => 'superseded', 'workflow_version' => $previous->workflow_version + 1])->save();
                    $this->audit->record($previous, $actor, 'renewed', from: $from, to: 'superseded', details: ['successor_id' => $document->id]);
                }
                app(IssuanceLifecycle::class)->completeIfReady($request, $actor);

                return $document;
            });
        } catch (\Throwable $error) {
            $this->copies->discard($newPath);
            if ($error instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['document' => 'تعارض إصدار متزامن أو رقم داخلي مستخدم. حدّث الصفحة للتحقق ثم أعد المحاولة.']);
            }
            throw $error;
        }
    }
}
