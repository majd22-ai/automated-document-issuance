<?php

namespace App\Services\Requirements;

use App\Enums\RequirementState as State;
use App\Models\CitizenDocument;
use App\Models\GovernmentService;
use App\Models\User;

class PriorDocumentRequirements
{
    public function evaluate(User $citizen, GovernmentService $service, ?CitizenDocument $selected = null): array
    {
        $documents = $citizen->documents()->where('document_type_code', $service->document_type)->when($selected, fn ($q) => $q->whereKey($selected->id))->orderByDesc('issued_at')->get();
        $policy = $service->verification_rules['renewal_validity'] ?? 'any';
        if (! in_array($policy, ['any', 'current', 'expired'], true) || ! $service->renewal_statuses) {
            return [new RequirementResult('previous_document', 'الوثيقة السابقة', State::Unverifiable, 'قواعد حالة الوثيقة أو صلاحيتها للتجديد غير مكتملة.'), null];
        }
        if ($documents->isEmpty()) {
            $unclassified = $citizen->documents()->whereNull('document_type_code')->exists();

            return [new RequirementResult('previous_document', 'الوثيقة السابقة', $unclassified ? State::Unverifiable : State::Unmet, $unclassified ? 'توجد سجلات وثائق غير مصنفة؛ يلزم تصنيفها من الجهة المختصة.' : 'لا توجد وثيقة سابقة مرتبطة بحسابك من النوع المطلوب.'), null];
        }
        $firstFailure = null;
        foreach ($documents as $document) {
            try {
                $issued = $document->issued_at;
                $expires = $document->expires_at;
            } catch (\Throwable) {
                $issued = null;
                $expires = null;
            }
            $validity = ! $issued || ! $expires ? 'incomplete' : ($expires->lt(today()) ? 'expired' : 'current');
            $details = ['present' => true, 'validity' => $validity, 'authenticity_verified' => false];
            if (! $issued || ! $expires || ! filled($document->status)) {
                $failure = new RequirementResult('previous_document', 'الوثيقة السابقة', State::Unverifiable, 'بيانات الوثيقة السابقة أو تاريخ صلاحيتها غير مكتملة.', details: $details);
            } elseif ($issued->gt(today()) || $expires->lt($issued)) {
                $failure = new RequirementResult('previous_document', 'الوثيقة السابقة', State::Unmet, 'تواريخ الوثيقة السابقة غير متسقة أو تاريخ إصدارها في المستقبل.', details: array_merge($details, ['validity' => 'invalid_dates']));
            } elseif (! in_array($document->status, $service->renewal_statuses, true)) {
                $failure = new RequirementResult('previous_document', 'الوثيقة السابقة', State::Unmet, 'حالة الوثيقة السابقة غير مقبولة وفق قواعد التجديد المسجلة.', details: $details);
            } elseif (($policy === 'current' && $validity !== 'current') || ($policy === 'expired' && $validity !== 'expired')) {
                $failure = new RequirementResult('previous_document', 'الوثيقة السابقة', State::Unmet, 'صلاحية الوثيقة السابقة لا تطابق قاعدة التجديد المسجلة.', details: $details);
            } elseif ($service->renewal_window_days !== null && $expires->gt(today()->addDays($service->renewal_window_days))) {
                $failure = new RequirementResult('previous_document', 'الوثيقة السابقة', State::Unmet, 'لم تبدأ نافذة التجديد المعتمدة لهذه الوثيقة بعد.', details: $details);
            } else {
                return [new RequirementResult('previous_document', 'الوثيقة السابقة', State::Met, 'سجل الوثيقة السابقة يستوفي فحوص الحالة والتواريخ؛ لا يعني ذلك التحقق من الأصالة.', details: $details), $document];
            }
            $firstFailure ??= $failure;
        }

        return [$firstFailure, null];
    }
}
