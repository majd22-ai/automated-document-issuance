<?php

namespace App\Http\Requests;

use App\Models\GovernmentService;
use App\Services\Integrations\IntegrationRules;
use App\Services\Issuance\IssuanceRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class SaveGovernmentServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', GovernmentService::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['application_fields', 'verification_rules', 'workflow_rules', 'issuance_rules', 'integration_rules'] as $jsonField) {
            if (is_string($this->input($jsonField))) {
                try {
                    $decoded = json_decode($this->input($jsonField), true, 32, JSON_THROW_ON_ERROR);
                    $this->merge([$jsonField => $decoded]);
                } catch (\JsonException) {
                    // Keep invalid text so normal validation reports the error; never silently discard it.
                }
            }
        }
        foreach (['conditions', 'required_documents', 'required_fields', 'renewal_statuses', 'terminal_request_statuses'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->input($field))), fn ($value) => $value !== ''))]);
            }
        }
    }

    public function rules(): array
    {
        $editing = $this->route('service') instanceof GovernmentService;

        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => $editing ? ['missing'] : ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9-]*$/', Rule::unique('government_services'), Rule::notIn(array_keys(GovernmentService::ALIASES))],
            'document_type' => $editing ? ['missing'] : ['required', Rule::in(array_keys(GovernmentService::DOCUMENT_TYPES))],
            'operation' => $editing ? ['missing'] : ['required', Rule::in(array_keys(GovernmentService::OPERATIONS))],
            'description' => ['nullable', 'string', 'max:10000'],
            'require_separate_approver' => ['sometimes', 'boolean'],
            'workflow_rules' => ['nullable', 'array:appointment,medical_stage,payment'],
            'workflow_rules.appointment' => ['sometimes', 'array:required,stage,statuses'],
            'workflow_rules.appointment.required' => ['required_with:workflow_rules.appointment', 'boolean'],
            'workflow_rules.appointment.stage' => ['required_with:workflow_rules.appointment', Rule::in(['accepted', 'approved', 'processing'])],
            'workflow_rules.appointment.statuses' => ['required_with:workflow_rules.appointment', 'array', 'min:1', 'max:3'],
            'workflow_rules.appointment.statuses.*' => [Rule::in(['booked', 'confirmed', 'completed'])],
            'workflow_rules.medical_stage' => ['sometimes', Rule::in(['accepted', 'approved', 'processing'])],
            'workflow_rules.payment' => ['sometimes', 'array:required,stage'],
            'workflow_rules.payment.required' => ['required_with:workflow_rules.payment', 'boolean'],
            'workflow_rules.payment.stage' => ['required_with:workflow_rules.payment', Rule::in(['accepted', 'approved', 'processing'])],
            'verification_rules' => ['nullable', 'array:documents,medical,renewal_validity,document_authenticity_required,restriction_source'],
            'verification_rules.documents' => ['sometimes', 'array', 'max:40'],
            'verification_rules.documents.*' => ['required', 'array:code,name,kind,allowed_mime_types,requires_review,requires_expiry,optional'],
            'verification_rules.documents.*.optional' => ['sometimes', 'boolean'],
            'verification_rules.documents.*.code' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,39}$/', 'distinct'],
            'verification_rules.documents.*.name' => ['required', 'string', 'max:500', 'distinct'],
            'verification_rules.documents.*.kind' => ['required', Rule::in(['photo', 'document'])],
            'verification_rules.documents.*.allowed_mime_types' => ['required', 'array', 'min:1', 'max:3'],
            'verification_rules.documents.*.allowed_mime_types.*' => [Rule::in(['image/jpeg', 'image/png', 'application/pdf'])],
            'verification_rules.documents.*.requires_review' => ['required', 'boolean'],
            'verification_rules.documents.*.requires_expiry' => ['sometimes', 'boolean'],
            'verification_rules.medical' => ['sometimes', 'array:required,requires_expiry'],
            'verification_rules.medical.required' => ['required_with:verification_rules.medical', 'boolean'],
            'verification_rules.medical.requires_expiry' => ['sometimes', 'boolean'],
            'verification_rules.renewal_validity' => ['sometimes', Rule::in(['any', 'current', 'expired'])],
            'verification_rules.document_authenticity_required' => ['sometimes', 'boolean'],
            'verification_rules.restriction_source' => ['sometimes', Rule::in(['manual', 'external'])],
            'application_fields' => ['nullable', 'array', 'max:20'],
            'application_fields.*' => ['required', 'array:key,label,type,required,max_length'],
            'application_fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,39}$/', 'distinct', Rule::notIn(['user_id', 'citizen_id', 'status', 'role', 'password', 'reference', 'service_id'])],
            'application_fields.*.label' => ['required', 'string', 'max:200'],
            'application_fields.*.type' => ['required', Rule::in(['text', 'textarea', 'date', 'integer'])],
            'application_fields.*.required' => ['required', 'boolean'],
            'application_fields.*.max_length' => ['sometimes', 'integer', 'min:1', 'max:2000'],
            'conditions' => ['nullable', 'array', 'max:40'], 'conditions.*' => ['required', 'string', 'max:500'],
            'required_documents' => ['nullable', 'array', 'max:40'], 'required_documents.*' => ['required', 'string', 'max:500'],
            'required_fields' => ['nullable', 'array', 'max:4'], 'required_fields.*' => [Rule::in(['name', 'username', 'email', 'phone'])],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'currency' => ['nullable', 'string', 'max:20'],
            'estimated_duration' => ['nullable', 'string', 'max:255'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'], 'rules_approved' => ['sometimes', 'boolean'],
            'minimum_age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'requires_driving_clearance' => ['sometimes', 'boolean'],
            'renewal_statuses' => ['nullable', 'array', 'max:30'], 'renewal_statuses.*' => ['required', 'string', 'max:100'],
            'renewal_window_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'terminal_request_statuses' => ['nullable', 'array', 'max:30'], 'terminal_request_statuses.*' => ['required', 'string', 'max:100'],
            'rules_version' => ['missing'],
            ...IssuanceRules::validation(), ...IntegrationRules::validation(),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $service = $this->route('service');
            $issuance = $this->input('issuance_rules');
            if (is_array($issuance) && ($issuance['enabled'] ?? false)) {
                try {
                    app(IssuanceRules::class)->get($issuance);
                } catch (ValidationException) {
                    $validator->errors()->add('issuance_rules', 'أكمل مرجع إعداد الإصدار ومدته ومراكز الاستلام.');
                }
            }
            $current = fn ($key, $default = null) => $this->input($key, $service instanceof GovernmentService ? $service->{$key} : $default);
            $verification = $current('verification_rules', []) ?? [];
            $workflow = $current('workflow_rules', []) ?? [];
            if (is_array($workflow) && isset($workflow['medical_stage']) && ($current('document_type') !== 'driving_license' || ! is_array($verification) || ! ($verification['medical']['required'] ?? false))) {
                $validator->errors()->add('workflow_rules', 'تأجيل الفحص يخص خدمة قيادة تشترط فحصًا طبيًا في قواعدها المعتمدة.');
            }
            if (is_array($verification)) {
                foreach (is_array($verification['documents'] ?? null) ? $verification['documents'] : [] as $rule) {
                    if (! is_array($rule)) {
                        continue;
                    }
                    if (! ($rule['optional'] ?? false) && ! in_array($rule['name'] ?? null, is_array($current('required_documents', [])) ? $current('required_documents', []) : [], true)) {
                        $validator->errors()->add('verification_rules', 'قاعدة المستند يجب أن تشير إلى اسم ضمن المستندات المطلوبة للخدمة.');
                    }
                    if (($rule['kind'] ?? null) === 'photo' && in_array('application/pdf', is_array($rule['allowed_mime_types'] ?? null) ? $rule['allowed_mime_types'] : [], true)) {
                        $validator->errors()->add('verification_rules', 'قاعدة الصورة الشخصية تقبل أنواع الصور فقط.');
                    }
                }
                if (array_key_exists('medical', $verification) && (! is_array($verification['medical']) || ! array_key_exists('required', $verification['medical']))) {
                    $validator->errors()->add('verification_rules', 'يجب تحديد ما إذا كان الفحص الطبي مطلوبًا عند إضافة قاعدته.');
                }
                if (($verification['medical']['required'] ?? false) && $current('document_type') !== 'driving_license') {
                    $validator->errors()->add('verification_rules', 'متطلب الفحص الطبي للقيادة يخص خدمات رخصة القيادة.');
                }
                if (($verification['document_authenticity_required'] ?? false) && $current('operation') !== 'renewal') {
                    $validator->errors()->add('verification_rules', 'قاعدة أصالة الوثيقة السابقة تخص خدمات التجديد.');
                }
            }
            if ($current('rules_approved', false) && ! filled($current('source_reference'))) {
                $validator->errors()->add('source_reference', 'يلزم مرجع اعتماد رسمي عند اعتماد شروط الخدمة.');
            }
            if ($current('is_active', false) && ! $current('rules_approved', false)) {
                $validator->errors()->add('rules_approved', 'اعتمد شروط الخدمة ومرجعها قبل تفعيلها.');
            }
            if ($current('fee') !== null && ! filled($current('currency'))) {
                $validator->errors()->add('currency', 'حدّد العملة عند تحديد الرسوم.');
            }
            if ($current('is_active', false) && $current('operation') === 'renewal' && ! $current('renewal_statuses')) {
                $validator->errors()->add('renewal_statuses', 'حدّد حالات الوثيقة السابقة المسموح بها قبل تفعيل خدمة التجديد.');
            }
        }];
    }

    public function messages(): array
    {
        return ['name.required' => 'اسم الخدمة مطلوب.', '*.missing' => 'لا يمكن تغيير معرّفات الخدمة أو إصدار قواعدها.', 'code.unique' => 'رمز الخدمة مستخدم مسبقًا.', 'fee.min' => 'الرسوم لا يمكن أن تكون سالبة.', 'code.regex' => 'استخدم حروفًا إنجليزية صغيرة وأرقامًا وشرطة في رمز الخدمة.'];
    }
}
