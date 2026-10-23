<?php

namespace App\Services\Issuance;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IssuanceRules
{
    public static function validation(string $prefix = 'issuance_rules'): array
    {
        return [
            $prefix => ['nullable', 'array:enabled,source_reference,expiry,number_prefix,include_year,electronic_copy,citizen_download,delivery,completion_stage'],
            "$prefix.enabled" => ['required_with:'.$prefix, 'boolean'], "$prefix.source_reference" => ['required_with:'.$prefix, 'string', 'max:255'],
            "$prefix.expiry" => ['required_with:'.$prefix, 'array:required,validity_days'], "$prefix.expiry.required" => ['required_with:'.$prefix, 'boolean'],
            "$prefix.expiry.validity_days" => ['nullable', 'required_if:'.$prefix.'.expiry.required,true', 'integer', 'min:1', 'max:36500'],
            "$prefix.number_prefix" => ['nullable', 'string', 'max:15', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/D'],
            "$prefix.include_year" => ['required_with:'.$prefix, 'boolean'], "$prefix.electronic_copy" => ['required_with:'.$prefix, Rule::in(['internal_html', 'none'])],
            "$prefix.citizen_download" => ['required_with:'.$prefix, 'boolean'], "$prefix.completion_stage" => ['required_with:'.$prefix, Rule::in(['issuance', 'delivery'])],
            "$prefix.delivery" => ['required_with:'.$prefix, 'array:methods,centers'], "$prefix.delivery.methods" => ['required_with:'.$prefix, 'array', 'min:1', 'max:2'],
            "$prefix.delivery.methods.*" => ['required', Rule::in(['pickup', 'address']), 'distinct'],
            "$prefix.delivery.centers" => ['sometimes', 'array', 'max:40'], "$prefix.delivery.centers.*" => ['required', 'string', 'max:200', 'distinct'],
        ];
    }

    public function get(?array $rules): array
    {
        $validator = Validator::make(['issuance_rules' => $rules], self::validation());
        if (! $rules || $validator->fails() || ! ($rules['enabled'] ?? false)
            || (in_array('pickup', $rules['delivery']['methods'] ?? [], true) && empty($rules['delivery']['centers']))
            || (($rules['expiry']['required'] ?? false) && empty($rules['expiry']['validity_days']))) {
            throw ValidationException::withMessages(['issuance' => 'إعدادات الإصدار أو مرجعها أو مدة الصلاحية أو مراكز الاستلام غير مكتملة. يلزم اعتمادها من المسؤول دون افتراض بيانات قانونية.']);
        }

        return $rules;
    }
}
