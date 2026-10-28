<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IdentityWorkflowAction extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->routeIs('identity-workflow.administration.decide') ? 'administer' : 'recordFingerprint';

        return $this->user()?->can($ability, $this->route('form')) ?? false;
    }

    public function rules(): array
    {
        $rules = ['version' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('identity-workflow.arrival')) {
            $rules += ['identity_checked' => ['accepted'], 'documents_checked' => ['accepted'], 'evidence_reference' => ['required', 'string', 'max:200']];
        } elseif ($this->routeIs('identity-workflow.security')) {
            $rules += ['paper_checked' => ['accepted'], 'evidence_reference' => ['required', 'string', 'max:200']];
        } elseif ($this->routeIs('identity-workflow.capture')) {
            $rules += ['operation_token' => ['required', 'uuid'], 'simulate_failure' => ['sometimes', 'boolean'], 'result' => ['missing']];
        } elseif ($this->routeIs('identity-workflow.manual')) {
            $rules += ['operation_token' => ['required', 'uuid'], 'result' => ['required', 'in:success,failure'], 'evidence_reference' => ['required', 'string', 'max:200'], 'executed_at' => ['required', 'date', 'before_or_equal:now'], 'manual_confirmed' => ['accepted']];
        } elseif ($this->routeIs('identity-workflow.administration.decide')) {
            $rules += ['action' => ['required', 'in:approved,returned_to_staff,correction_required,rejected'], 'decision_token' => ['required', 'uuid'], 'internal_note' => ['required_if:action,returned_to_staff', 'nullable', 'string', 'max:2000'], 'citizen_reason' => ['required_if:action,correction_required,rejected', 'nullable', 'string', 'max:1500']];
        } elseif ($this->routeIs('identity-workflow.administration.requeue')) {
            $rules += ['note' => ['required', 'string', 'max:2000']];
        }
        foreach (['source', 'mode', 'recorded_by', 'user_id', 'fingerprint_template', 'fingerprint_image', 'biometric_data', 'status'] as $field) {
            $rules[$field] = ['missing'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['*.required' => 'هذا الحقل مطلوب.', '*.accepted' => 'يجب تأكيد فحص الإجراء قبل تسجيله.', '*.uuid' => 'رمز العملية غير صحيح. حدّث الصفحة.', '*.missing' => 'مصدر النتيجة والحالة والموظف يحددها الخادم؛ لا تُقبل بيانات بصمة حيوية.', '*.before_or_equal' => 'وقت الإجراء لا يمكن أن يكون في المستقبل.', '*.max' => 'النص يتجاوز الحد المسموح.', 'citizen_reason.required_if' => 'أدخل السبب المسموح بعرضه للمواطن.', 'internal_note.required_if' => 'أدخل ملاحظة داخلية للموظف عند إعادة الطلب.'];
    }
}
