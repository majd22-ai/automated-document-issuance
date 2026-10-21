<?php

namespace App\Services\Requirements;

use App\Contracts\ServiceEvidenceSource;
use App\Enums\RequirementState as State;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class EvidenceRequirements
{
    public function __construct(private ServiceEvidenceSource $source) {}

    private function identifier(mixed $value): ?int
    {
        if (! is_int($value) && (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/', $value))) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id !== false && $id > 0 ? $id : null;
    }

    private function lookup(User $citizen, GovernmentService $service, string $kind, string $key, ?CitizenServiceRequest $request): EvidenceLookup
    {
        try {
            $lookup = $this->source->lookup($citizen, $service, $kind, $key, $request);
            $records = array_values(array_filter($lookup->records, fn ($record) => is_array($record)
                && $this->identifier($record['user_id'] ?? null) === $citizen->id
                && $this->identifier($record['service_id'] ?? null) === $service->id
                && (($record['request_id'] ?? null) === null || ($request && $this->identifier($record['request_id']) === $request->id))));

            return new EvidenceLookup($lookup->available, $records);
        } catch (\Throwable $exception) {
            // Do not log URLs, tokens, health information, remote messages or returned records.
            Log::warning('service_evidence_source_unavailable', ['kind' => $kind, 'error_type' => get_class($exception)]);

            return new EvidenceLookup;
        }
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ][0-9:.+Z-]+)?$/', $value, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function trusted(array $record): bool
    {
        $verified = $this->date($record['verified_at'] ?? null);
        if (array_key_exists('valid_until', $record)) {
            $validUntil = $this->date($record['valid_until']);
            if (! $validUntil || $validUntil->lessThan(now())) {
                return false;
            }
        }

        return ($record['trusted'] ?? false) === true && is_string($record['source_reference'] ?? null)
            && trim($record['source_reference']) !== '' && $verified && $verified->lessThanOrEqualTo(now());
    }

    public function attachment(User $citizen, GovernmentService $service, array $rule, ?CitizenServiceRequest $request): RequirementResult
    {
        $code = 'attachment.'.$rule['code'];
        $label = $rule['name'];
        $details = ['uploaded' => null, 'type_matches' => null, 'verified' => null, 'review_required' => $rule['requires_review']];
        $lookup = $this->lookup($citizen, $service, 'attachment', $rule['code'], $request);
        if (! $lookup->available) {
            return new RequirementResult($code, $label, State::Unverifiable, 'تعذّر التحقق من '.$label.'؛ وحدة المرفقات أو مصدرها غير متاح.', 'external', details: $details);
        }
        $records = array_values(array_filter($lookup->records, fn ($record) => ($record['uploaded'] ?? false) === true));
        if (! $records) {
            return new RequirementResult($code, $label, State::Unmet, 'المستند المطلوب غير مرفوع: '.$label.'.', 'citizen', details: array_merge($details, ['uploaded' => false]));
        }
        $candidates = [];
        foreach ($records as $record) {
            if (! $this->trusted($record)) {
                $candidates[] = new RequirementResult($code, $label, State::Unverifiable, 'لا يوجد مصدر موثق لبيانات رفع ونوع المستند: '.$label.'.', 'external', details: $details);

                continue;
            }
            $typeMatches = in_array($record['mime_type'] ?? null, $rule['allowed_mime_types'], true);
            $current = array_merge($details, ['uploaded' => true, 'type_matches' => $typeMatches, 'verified' => false]);
            if (in_array($record['review_status'] ?? null, ['rejected', 'replacement_required'], true)) {
                $candidates[] = new RequirementResult($code, $label, State::Unmet, 'المرفق مرفوض أو يحتاج إلى استبدال: '.$label.'.', 'citizen', details: $current);

                continue;
            }
            if (! $typeMatches) {
                $candidates[] = new RequirementResult($code, $label, State::Unmet, 'نوع المستند غير مناسب: '.$label.'.', 'citizen', details: $current);

                continue;
            }
            $expires = $this->date($record['expires_at'] ?? null);
            if ((($rule['requires_expiry'] ?? false) || ($record['expires_at'] ?? null) !== null) && ! $expires) {
                $candidates[] = new RequirementResult($code, $label, State::Unverifiable, 'تاريخ صلاحية المستند غير متوفر: '.$label.'.', details: $current);

                continue;
            }
            if ($expires && $expires->startOfDay()->lessThan(today())) {
                $candidates[] = new RequirementResult($code, $label, State::Unmet, 'المستند منتهي الصلاحية: '.$label.'.', 'citizen', details: $current);

                continue;
            }
            if ($rule['requires_review']) {
                if (($record['review_status'] ?? null) === 'rejected') {
                    $candidates[] = new RequirementResult($code, $label, State::Unmet, 'لم تعتمد مراجعة المستند: '.$label.'.', 'staff', details: $current);

                    continue;
                }
                if (($record['review_status'] ?? null) !== 'approved' || ! $this->trusted($record)) {
                    $candidates[] = new RequirementResult($code, $label, State::NeedsReview, 'المستند مرفوع ويحتاج مراجعة معتمدة: '.$label.'.', 'staff', details: $current);

                    continue;
                }
            }

            return new RequirementResult($code, $label, State::Met, 'المستند يستوفي فحوص الرفع والنوع والمراجعة المطلوبة: '.$label.'.', details: array_merge($current, ['verified' => $rule['requires_review']]));
        }
        usort($candidates, fn ($a, $b) => [State::NeedsReview->value => 0, State::Unverifiable->value => 1, State::Unmet->value => 2][$a->state->value] <=> [State::NeedsReview->value => 0, State::Unverifiable->value => 1, State::Unmet->value => 2][$b->state->value]);

        return $candidates[0];
    }

    public function medical(User $citizen, GovernmentService $service, array $rule, ?CitizenServiceRequest $request): RequirementResult
    {
        $lookup = $this->lookup($citizen, $service, 'medical', 'driving', $request);
        if (! $lookup->available) {
            return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unverifiable, 'مصدر الفحص الطبي غير متاح؛ يلزم تحقق موثّق من الجهة المختصة.', 'external');
        }
        $record = $lookup->records[0] ?? null;
        if (! $record) {
            return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unmet, 'نتيجة الفحص الطبي المطلوبة غير موجودة لدى المصدر المتاح.');
        }
        if (! $this->trusted($record)) {
            return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unverifiable, 'لا يوجد توثيق معتمد لنتيجة الفحص الطبي.');
        }
        if (($record['result'] ?? null) === 'pending') {
            return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::NeedsReview, 'نتيجة الفحص الطبي قيد المراجعة.');
        }
        if (($record['result'] ?? null) === 'failed') {
            return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unmet, 'نتيجة الفحص الطبي لا تستوفي المتطلب المسجل.');
        }
        $examined = $this->date($record['examined_at'] ?? null);
        $expires = $this->date($record['expires_at'] ?? null);
        if (($record['result'] ?? null) !== 'passed' || ! $examined || $examined->startOfDay()->greaterThan(today()) || ((($rule['requires_expiry'] ?? false) || ($record['expires_at'] ?? null) !== null) && ! $expires)) {
            return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unverifiable, 'بيانات نتيجة الفحص الطبي أو تواريخها غير مكتملة.');
        }
        if ($expires && ($expires->startOfDay()->lessThan(today()) || $expires->lessThan($examined))) {
            return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Unmet, 'الفحص الطبي منتهي الصلاحية أو تواريخه غير متسقة.');
        }

        return new RequirementResult('medical', 'الفحص الطبي للقيادة', State::Met, 'نتيجة فحص موثقة تستوفي القاعدة المسجلة.');
    }

    public function authenticity(User $citizen, GovernmentService $service, ?CitizenDocument $document, ?CitizenServiceRequest $request): RequirementResult
    {
        $lookup = $document ? $this->lookup($citizen, $service, 'document', (string) $document->id, $request) : new EvidenceLookup;
        $record = array_values(array_filter($lookup->records, fn ($record) => $this->identifier($record['document_id'] ?? null) === $document?->id))[0] ?? null;
        if (! $lookup->available || ! $record || ! $this->trusted($record)) {
            return new RequirementResult('document_authenticity', 'أصالة الوثيقة السابقة', State::Unverifiable, 'تعذّر التحقق من أصالة الوثيقة لدى مصدر موثوق؛ وجود السجل وحده لا يثبت الأصالة.', 'external');
        }

        return new RequirementResult('document_authenticity', 'أصالة الوثيقة السابقة', match ($record['result'] ?? null) {
            'verified' => State::Met, 'invalid' => State::Unmet, 'pending' => State::NeedsReview, default => State::Unverifiable
        }, 'حالة التحقق من أصالة الوثيقة تعتمد على المصدر المعتمد.');
    }

    public function restrictions(User $citizen, GovernmentService $service, ?CitizenServiceRequest $request): RequirementResult
    {
        $lookup = $this->lookup($citizen, $service, 'restrictions', 'clearance', $request);
        foreach ($lookup->records as $candidate) {
            if ($lookup->available && $this->trusted($candidate) && ($candidate['result'] ?? null) === 'blocked') {
                return new RequirementResult('restrictions', 'التحقق من الموانع', State::Unmet, 'يوجد مانع معتمد؛ راجع الجهة المختصة.');
            }
        }
        $record = $lookup->records[0] ?? null;
        if (! $lookup->available || ! $record || ! $this->trusted($record)) {
            return new RequirementResult('restrictions', 'التحقق من الموانع', State::Unverifiable, 'تعذّر التحقق من مصدر الموانع المعتمد؛ لا يمكن اعتباره خلوًا من الموانع.', 'external');
        }
        if (($record['result'] ?? null) === 'clear') {
            return new RequirementResult('restrictions', 'التحقق من الموانع', State::Met, 'المصدر المعتمد أعاد نتيجة خلو من الموانع لهذه الخدمة.');
        }
        if (($record['result'] ?? null) === 'blocked') {
            return new RequirementResult('restrictions', 'التحقق من الموانع', State::Unmet, 'يوجد مانع معتمد؛ راجع الجهة المختصة.');
        }

        return new RequirementResult('restrictions', 'التحقق من الموانع', State::NeedsReview, 'التحقق من الموانع يحتاج مراجعة الجهة المختصة.');
    }
}
