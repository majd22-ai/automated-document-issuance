<?php

namespace App\Services\Attachments;

use App\Models\GovernmentService;
use Illuminate\Validation\ValidationException;

class AttachmentRules
{
    public function all(GovernmentService $service): array
    {
        $result = [];
        foreach ($service->verification_rules['documents'] ?? [] as $rule) {
            if (! is_array($rule) || ! is_string($rule['code'] ?? null) || ! preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $rule['code'])
                || ! is_string($rule['name'] ?? null) || ! in_array($rule['kind'] ?? null, ['photo', 'document'], true)
                || ! is_bool($rule['requires_review'] ?? null) || ! is_array($rule['allowed_mime_types'] ?? null)
                || ! $rule['allowed_mime_types'] || array_diff($rule['allowed_mime_types'], ['image/jpeg', 'image/png', 'application/pdf'])
                || ($rule['kind'] === 'photo' && in_array('application/pdf', $rule['allowed_mime_types'], true))) {
                continue;
            }
            $rule['mandatory'] = in_array($rule['name'], $service->required_documents ?? [], true);
            $rule['max_kb'] = max(1, (int) config('attachments.'.$rule['kind'].'_max_kb'));
            $result[$rule['code']] = $rule;
        }

        return $result;
    }

    public function get(GovernmentService $service, string $code): array
    {
        return $this->all($service)[$code] ?? throw ValidationException::withMessages(['document_code' => 'نوع المستند غير معرف أو لم تعتمد قواعده لهذه الخدمة.']);
    }
}
