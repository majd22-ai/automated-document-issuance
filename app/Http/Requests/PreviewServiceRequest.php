<?php

namespace App\Http\Requests;

use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CitizenServiceRequest::class) ?? false;
    }

    public static function payloadRules(GovernmentService $service): array
    {
        $fields = $service->application_fields ?? [];
        if ($service->code === 'identity-first-issue') {
            foreach (config('identity_card.fields') as $group) {
                foreach ($group as $key => $field) {
                    if (! in_array($key, array_column($fields, 'key'), true)) {
                        $fields[] = ['key' => $key, 'type' => ($field['type'] ?? '') === 'date' ? 'date' : 'text', 'required' => false, 'max_length' => 200];
                    }
                }
            }
        }
        $rules = ['data' => ['sometimes', 'array', $fields ? 'array:'.implode(',', array_column($fields, 'key')) : 'size:0'], 'notes' => ['nullable', 'string', 'max:2000']];
        foreach ($fields as $field) {
            $rules['data.'.$field['key']] = [($field['required'] ?? false) ? 'required' : 'nullable', ...match ($field['type']) {
                'text', 'textarea' => ['string', 'max:'.($field['max_length'] ?? 1000)],
                'integer' => ['integer'], 'date' => ['date_format:Y-m-d'], default => ['prohibited'],
            }];
        }
        if ($service->code === 'birth-certificate-first-issue') {
            $rules['data.child_gender'] = ['required', Rule::in(['ذكر', 'أنثى'])];
            $rules['data.birth_date'][] = 'before_or_equal:today';
            $rules['data.birth_record_date'] = ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
        }

        return $rules;
    }

    public function rules(): array
    {
        $rules = self::payloadRules($this->route('service'));
        foreach (['user_id', 'citizen_id', 'government_service_id', 'service_id', 'status', 'reference', 'request_type', 'submission_token', 'active_request_key'] as $field) {
            $rules[$field] = ['missing'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['data.*.required' => 'أكمل بيانات الطلب المطلوبة للخدمة.', 'data.array' => 'بيانات الطلب تحتوي حقولًا غير مسموح بها.', '*.missing' => 'هوية المواطن والخدمة والحالة يحددها الخادم فقط.'];
    }
}
