<?php

namespace App\Services\Integrations;

use App\Models\CitizenServiceRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IntegrationRules
{
    public const KINDS = ['identity' => 'الهوية', 'document' => 'الوثيقة السابقة', 'medical' => 'الفحص الطبي', 'payment' => 'حالة السداد'];

    public static function validation(): array
    {
        return ['integration_rules' => ['nullable', 'array:identity,document,medical,payment'],
            'integration_rules.*' => ['required', 'array:required,provider,max_age_minutes'],
            'integration_rules.*.required' => ['required', 'boolean'], 'integration_rules.*.provider' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,59}$/D'],
            'integration_rules.*.max_age_minutes' => ['required', 'integer', 'min:1', 'max:10080']];
    }

    public function effective(CitizenServiceRequest $request): array
    {
        $current = $request->service?->integration_rules ?? [];
        $snapshot = $request->service_snapshot['integration_rules'] ?? [];
        foreach ([$current, $snapshot] as $rules) {
            Validator::make(['integration_rules' => $rules], self::validation())->validate();
        }
        $effective = $current;
        foreach ($snapshot as $kind => $rule) {
            if (! ($rule['required'] ?? false)) {
                continue;
            }
            if (($current[$kind]['required'] ?? false) && ($current[$kind]['provider'] !== $rule['provider'])) {
                throw ValidationException::withMessages(['integration_rules' => 'تعارض مصدر التحقق الإلزامي الحالي مع السياسة التاريخية؛ يلزم إجراء إداري موثق.']);
            }
            $effective[$kind] = array_merge($rule, ['max_age_minutes' => min($rule['max_age_minutes'], $current[$kind]['max_age_minutes'] ?? $rule['max_age_minutes'])]);
        }

        return $effective;
    }
}
