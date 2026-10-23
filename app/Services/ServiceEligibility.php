<?php

namespace App\Services;

use App\Enums\RequirementState as State;
use App\Enums\ServiceRequestStatus;
use App\Http\Requests\PreviewServiceRequest;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Operations\RequestPrerequisites;
use App\Services\Requirements\EligibilityResult;
use App\Services\Requirements\EvidenceRequirements;
use App\Services\Requirements\PriorDocumentRequirements;
use App\Services\Requirements\RequirementResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ServiceEligibility
{
    public function __construct(private EvidenceRequirements $evidence, private PriorDocumentRequirements $priorDocuments, private RequestPrerequisites $prerequisites) {}

    /** Compatibility for existing callers; structured evaluate() is the single source of truth. */
    public function reasons(User $citizen, GovernmentService $service): array
    {
        return $this->evaluate($citizen, $service)->reasons();
    }

    public function evaluate(User $citizen, GovernmentService $service, ?CitizenServiceRequest $request = null, ?ServiceRequestStatus $target = null): EligibilityResult
    {
        $report = new EligibilityResult;
        if ($request && ($request->user_id !== $citizen->id || $request->government_service_id !== $service->id)) {
            $report->add(new RequirementResult('context', 'سياق الطلب', State::Unmet, 'الطلب لا يرتبط بالمواطن والخدمة المحددين.'));

            return $report;
        }
        $active = $citizen->role === 'citizen' && $citizen->is_active;
        $report->add(new RequirementResult('account', 'حساب المواطن', $active ? State::Met : State::Unmet, $active ? 'الحساب مفعّل بدور مواطن.' : 'بدء الخدمة متاح لحساب مواطن مفعّل فقط.'));
        if (! $active) {
            return $report;
        }
        $report->add(new RequirementResult('service_active', 'توفر الخدمة', $service->is_active ? State::Met : State::Unmet, $service->is_active ? 'الخدمة مفعّلة.' : 'الخدمة غير مفعّلة حاليًا.'));
        $approved = $service->rules_approved && filled($service->source_reference);
        $report->add(new RequirementResult('service_rules', 'اعتماد قواعد الخدمة', $approved ? State::Met : State::NeedsReview, $approved ? 'بيانات الخدمة معتمدة في المنصة.' : 'شروط الخدمة لم تعتمد بعد من الجهة المختصة.'));
        $missing = $citizen->missingCitizenFields();
        foreach ($service->required_fields ?? [] as $field) {
            if (! in_array($field, ['name', 'username', 'email', 'phone'], true) || ! filled($citizen->{$field})) {
                $missing[] = ['phone' => 'رقم الهاتف', 'name' => 'الاسم', 'username' => 'الرقم الوطني', 'email' => 'البريد الإلكتروني'][$field] ?? 'حقل معتمد للخدمة';
            }
        }
        $report->add(new RequirementResult('citizen_data', 'بيانات المواطن المطلوبة', $missing ? State::Unmet : State::Met, $missing ? 'البيانات المطلوبة غير مكتملة: '.implode('، ', array_unique($missing)) : 'الحقول الأساسية المطلوبة مكتملة.', 'citizen'));
        if ($request) {
            $schema = new GovernmentService;
            $schema->application_fields = $request->service_snapshot['application_fields'] ?? [];
            $valid = Validator::make(['data' => $request->request_data ?? [], 'notes' => $request->notes], PreviewServiceRequest::payloadRules($schema))->passes();
            $report->add(new RequirementResult('request_data', 'بيانات نموذج الطلب', $valid ? State::Met : State::Unmet, $valid ? 'حقول الطلب المسجلة تستوفي النموذج.' : 'بيانات نموذج الطلب الإلزامية ناقصة أو غير صالحة.', 'citizen'));
        }
        $assessment = $service->assessments()->where('user_id', $citizen->id)->first();
        $current = $assessment && $assessment->rules_version === $service->rules_version
            && filled($assessment->verification_reference) && $assessment->reviewed_by && $assessment->reviewed_at && $assessment->reviewed_at->lte(now());
        foreach (['identity' => ['identity_verified', 'توثيق ارتباط الهوية بالحساب'], 'conditions' => ['conditions_met', 'استيفاء الشروط المسجلة']] as $code => [$field, $label]) {
            $met = $current && $assessment->{$field};
            $report->add(new RequirementResult($code, $label, $met ? State::Met : State::NeedsReview, $met ? $label.' موثق وفق التقييم المسجل.' : 'يلزم تقييم موثّق أو تحديث تقييم '.$label.' لدى الجهة المختصة.'));
        }
        if ($service->minimum_age !== null) {
            $met = $current && $assessment->age_requirement_met;
            $report->add(new RequirementResult('age', 'شرط العمر', $met ? State::Met : State::NeedsReview, $met ? 'شرط العمر المسجل تم التحقق منه يدويًا وفق المرجع.' : 'لم يتم اعتماد استيفاء شرط العمر المسجل للخدمة.'));
        }
        if ($service->requires_driving_clearance) {
            $met = $current && $assessment->driving_requirements_met;
            $report->add(new RequirementResult('driving_clearance', 'متطلبات القيادة المسجلة', $met ? State::Met : State::NeedsReview, $met ? 'متطلبات القيادة العامة موثقة؛ لا تغني عن فحص طبي مستقل عند اشتراطه.' : 'متطلبات رخصة القيادة لم تعتمد بعد من الجهة المختصة.'));
        }
        $rules = $service->verification_rules ?? [];
        if (! is_array($rules) || array_diff(array_keys($rules), ['documents', 'medical', 'renewal_validity', 'document_authenticity_required', 'restriction_source'])) {
            $report->add(new RequirementResult('verification_configuration', 'قواعد التحقق', State::Unverifiable, 'بنية قواعد التحقق غير صالحة وتحتاج مراجعة الإدارة.'));

            return $report;
        }
        if ($assessment?->is_blocked) {
            $report->add(new RequirementResult('restrictions', 'التحقق من الموانع', State::Unmet, 'يوجد مانع معتمد: '.($assessment->public_block_reason ?: 'راجع الجهة المختصة.')));
        } elseif (($rules['restriction_source'] ?? 'manual') === 'external') {
            $report->add($this->evidence->restrictions($citizen, $service, $request));
        } elseif (($rules['restriction_source'] ?? 'manual') === 'manual') {
            $report->add(new RequirementResult('restrictions', 'التحقق من الموانع', $current ? State::Met : State::Unverifiable, $current ? 'التقييم اليدوي الموثق لا يسجل مانعًا لهذه الخدمة.' : 'تعذّر التحقق من الموانع دون تقييم موثق حالي؛ غياب البيانات ليس دليلًا على الخلو.'));
        } else {
            $report->add(new RequirementResult('restrictions', 'التحقق من الموانع', State::Unverifiable, 'نوع مصدر الموانع غير معرّف بصورة صحيحة.'));
        }
        if ($service->operation === 'renewal') {
            [$check, $document] = $this->priorDocuments->evaluate($citizen, $service);
            $report->add($check);
            if ($rules['document_authenticity_required'] ?? false) {
                $report->add($this->evidence->authenticity($citizen, $service, $document, $request));
            }
        }
        $documentRules = $rules['documents'] ?? [];
        if (! is_array($documentRules) || count(array_column($documentRules, 'code')) !== count(array_unique(array_column($documentRules, 'code')))) {
            $report->add(new RequirementResult('document_configuration', 'قواعد المستندات', State::Unverifiable, 'بنية قواعد المستندات أو رموزها غير صالحة.'));
            $documentRules = [];
        }
        $configured = array_column($documentRules, null, 'name');
        foreach ($service->required_documents ?? [] as $name) {
            $rule = $configured[$name] ?? null;
            $valid = is_array($rule) && filled($rule['code'] ?? null) && in_array($rule['kind'] ?? null, ['photo', 'document'], true)
                && is_array($rule['allowed_mime_types'] ?? null) && $rule['allowed_mime_types'] !== [] && is_bool($rule['requires_review'] ?? null);
            $valid = $valid && ! array_diff($rule['allowed_mime_types'], ['image/jpeg', 'image/png', 'application/pdf'])
                && ($rule['kind'] !== 'photo' || ! in_array('application/pdf', $rule['allowed_mime_types'], true));
            if (! $valid) {
                $report->add(new RequirementResult('attachment.'.substr(hash('sha256', $name), 0, 16), $name, State::Unverifiable, 'لم تعتمد قاعدة النوع والمراجعة للمستند المطلوب: '.$name.'.'));
            } else {
                $report->add($this->evidence->attachment($citizen, $service, $rule, $request));
            }
        }
        if (array_key_exists('medical', $rules) && (! is_array($rules['medical']) || ! array_key_exists('required', $rules['medical']) || ! is_bool($rules['medical']['required']))) {
            $report->add(new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unverifiable, 'قاعدة الفحص الطبي غير مكتملة أو غير صالحة.'));
        } elseif ($rules['medical']['required'] ?? false) {
            $report->add($service->document_type === 'driving_license' && ! $this->prerequisites->medicalDue($service, $request, $target)
                ? new RequirementResult('medical', 'الفحص الطبي للقيادة', State::NeedsReview, 'يلزم تسجيل نتيجة فحص موثقة قبل المرحلة المحددة في قواعد الخدمة.', mandatory: false)
                : ($service->document_type === 'driving_license'
                ? $this->evidence->medical($citizen, $service, $rules['medical'], $request)
                : new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unverifiable, 'قاعدة الفحص الطبي غير متوافقة مع نوع الخدمة.')));
        }
        $requests = $citizen->serviceRequests()->where(function ($query) use ($service) {
            $query->where('government_service_id', $service->id)->orWhereIn('service_code', $service->codes());
        });
        if ($request) {
            $requests->whereKeyNot($request->id);
        }
        $terminal = $service->terminalStatuses();
        $requests->where(fn ($query) => $query->whereNull('status')->orWhereNotIn('status', $terminal));
        $duplicate = $requests->exists();
        $report->add(new RequirementResult('duplicate_request', 'عدم تكرار الطلب', $duplicate ? State::Unmet : State::Met, $duplicate ? 'يوجد طلب قائم للخدمة نفسها؛ راجع طلباتك قبل البدء مجددًا.' : 'لا يوجد طلب قائم آخر يمنع المتابعة.', 'citizen'));

        foreach ($this->prerequisites->checks($service, $request, $target) as $check) {
            $report->add($check);
        }

        return $report;
    }

    public function audit(EligibilityResult $report, User $citizen, GovernmentService $service, ?CitizenServiceRequest $request = null): void
    {
        Log::info('service_requirements_checked', [
            'citizen_id' => $citizen->id, 'service_id' => $service->id, 'request_id' => $request?->id,
            'rules_version' => $service->rules_version, 'eligible' => $report->eligible(),
            'checks' => array_values(array_map(fn ($check) => ['code' => $check->code, 'state' => $check->state->value, 'mandatory' => $check->mandatory], $report->checks)),
        ]);
    }
}
