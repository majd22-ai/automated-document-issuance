<?php

namespace App\Services\Review;

use App\Enums\RequirementState;
use App\Http\Requests\PreviewServiceRequest;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\RequestDeficiency;
use App\Models\ReviewDecision;
use App\Services\ServiceEligibility;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReviewDeficiencies
{
    public function validateReturn(CitizenServiceRequest $request, array $items): void
    {
        if (! $items || count($items) > 20) {
            throw ValidationException::withMessages(['deficiencies' => 'حدد نقصًا واضحًا واحدًا على الأقل، وبحد أقصى عشرين.']);
        }
        $fields = array_column($request->service_snapshot['application_fields'] ?? [], 'key');
        $attachments = array_column($request->service_snapshot['verification_rules']['documents'] ?? [], 'code');
        foreach ($items as $item) {
            if (! is_array($item) || ! in_array($item['target_type'] ?? null, ['field', 'attachment', 'requirement', 'other'], true) || ! is_string($item['target_key'] ?? null) || ! is_string($item['description'] ?? null) || trim($item['description']) === '') {
                throw ValidationException::withMessages(['deficiencies' => 'تعريف النقص غير صالح.']);
            }
            if (($item['target_type'] === 'field' && ! in_array($item['target_key'], $fields, true)) || ($item['target_type'] === 'attachment' && ! in_array($item['target_key'], $attachments, true))) {
                throw ValidationException::withMessages(['deficiencies' => 'الحقل أو المرفق المشار إليه لا ينتمي إلى نموذج هذا الطلب.']);
            }
            if ($item['target_type'] === 'requirement' && ! isset(app(ServiceEligibility::class)->evaluate($request->citizen, $request->service, $request)->checks[$item['target_key']])) {
                throw ValidationException::withMessages(['deficiencies' => 'رمز الشرط غير معروف لهذا الطلب.']);
            }
        }
    }

    public function create(CitizenServiceRequest $request, ReviewDecision $decision, array $items): void
    {
        foreach ($items as $item) {
            (new RequestDeficiency)->forceFill(['citizen_service_request_id' => $request->id, 'review_decision_id' => $decision->id, 'target_type' => $item['target_type'], 'target_key' => $item['target_key'], 'description' => $item['description'], 'status' => 'open'])->save();
        }
    }

    public function correctionData(CitizenServiceRequest $request, array $context): array
    {
        $open = $request->deficiencies()->where('status', '!=', 'resolved')->get();
        $allowed = $open->where('target_type', 'field')->pluck('target_key')->all();
        $data = $context['data'] ?? [];
        if (! is_array($data) || array_diff(array_keys($data), $allowed)) {
            throw ValidationException::withMessages(['data' => 'يمكن تعديل الحقول المحددة في النواقص فقط؛ البيانات الرسمية وهوية الطلب ثابتة.']);
        }
        $responses = $context['responses'] ?? [];
        if (! is_array($responses) || array_diff(array_map('strval', array_keys($responses)), $open->pluck('id')->map(fn ($id) => (string) $id)->all())) {
            throw ValidationException::withMessages(['responses' => 'ردود النواقص يجب أن تخص هذا الطلب فقط.']);
        }
        foreach ($open as $row) {
            $response = $responses[$row->id] ?? null;
            if (! is_string($response) || mb_strlen(trim($response)) < 3 || mb_strlen($response) > 500) {
                throw ValidationException::withMessages(['responses' => 'وضح الإجراء الذي اتخذته لكل نقص مطلوب.']);
            }
            $row->forceFill(['citizen_response' => $response, 'status' => 'awaiting_review'])->save();
        }
        $merged = array_replace($request->request_data ?? [], $data);
        $schema = new GovernmentService;
        $schema->application_fields = $request->service_snapshot['application_fields'] ?? [];
        Validator::make(['data' => $merged, 'notes' => $request->notes], PreviewServiceRequest::payloadRules($schema))->validate();

        return $merged;
    }

    public function assertResolved(CitizenServiceRequest $request): void
    {
        if ($request->deficiencies()->where('status', '!=', 'resolved')->exists()) {
            throw ValidationException::withMessages(['deficiencies' => 'توجد نواقص لم يتحقق الموظف من استكمالها بعد.']);
        }
    }

    public function assertResolution(CitizenServiceRequest $request, RequestDeficiency $row): void
    {
        if ($row->target_type === 'field') {
            $schema = new GovernmentService;
            $schema->application_fields = $request->service_snapshot['application_fields'] ?? [];
            Validator::make(['data' => $request->request_data ?? [], 'notes' => $request->notes], PreviewServiceRequest::payloadRules($schema))->validate();
        } elseif (in_array($row->target_type, ['attachment', 'requirement'], true)) {
            $code = $row->target_type === 'attachment' ? 'attachment.'.$row->target_key : $row->target_key;
            $check = app(ServiceEligibility::class)->evaluate($request->citizen, $request->service, $request)->checks[$code] ?? null;
            if (! $check || $check->state !== RequirementState::Met) {
                throw ValidationException::withMessages(['deficiencies' => 'الشرط أو المرفق المحدد لم يستوف التحقق بعد.']);
            }
        }
    }
}
