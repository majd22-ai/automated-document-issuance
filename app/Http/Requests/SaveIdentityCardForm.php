<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveIdentityCardForm extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->role === 'citizen'
            && (int) $this->route('form')?->user_id === (int) $this->user()->id;
    }

    public static function dataRules(?int $step = null, bool $complete = true): array
    {
        $rules = [];
        foreach (config('identity_card.fields') as $number => $fields) {
            if ($step && $step !== $number) {
                continue;
            }
            foreach ($fields as $key => $field) {
                $rule = [($complete && $field['required']) ? 'required' : 'nullable'];
                if (($field['type'] ?? '') === 'date') {
                    $rule = [...$rule, 'date_format:Y-m-d', 'before_or_equal:today'];
                } else {
                    $rule = [...$rule, 'string', 'max:200'];
                }
                if (isset($field['options'])) {
                    $rule[] = Rule::in(array_keys($field['options']));
                }
                if (in_array($key, ['phone', 'alternate_phone'], true)) {
                    $rule[] = 'regex:/^\+?[0-9]{7,15}$/';
                }
                $rules['data.'.$key] = $rule;
            }
        }

        return $rules;
    }

    public static function witnessRules(bool $complete): array
    {
        $rules = ['witnesses' => [$complete ? 'required' : 'sometimes', 'array', 'list', 'size:2']];
        foreach (['name', 'document_type', 'document_number', 'issued_place', 'phone', 'address', 'employer', 'work_address'] as $key) {
            $rules['witnesses.*.'.$key] = [$complete && ! in_array($key, ['employer', 'work_address']) ? 'required' : 'nullable', 'string', 'max:200'];
        }
        $rules['witnesses.*.issued_at'] = [$complete ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
        $rules['witnesses.*.phone'][] = 'regex:/^\+?[0-9]{7,15}$/';
        $rules['witnesses.1.document_number'][] = 'different:witnesses.0.document_number';
        $rules['witnesses.*'] = ['array:name,document_type,document_number,issued_place,issued_at,employer,work_address,phone,address'];

        return $rules;
    }

    public function rules(): array
    {
        $step = (int) $this->input('step');
        $complete = $this->input('action') === 'next';
        $rules = ['step' => ['required', 'integer', 'between:1,6'], 'action' => ['required', 'in:save,next'], 'version' => ['required', 'integer', 'min:1']];
        if ($step >= 1 && $step <= 3) {
            $fields = array_keys(config('identity_card.fields.'.$step));
            $rules['data'] = ['present', 'array:'.implode(',', $fields)];
            $rules += self::dataRules($step, $complete);
        }
        if ($step === 4) {
            $rules['photo'] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:'.config('identity_card.photo.max_kb'), 'dimensions:min_width='.config('identity_card.photo.min_width').',min_height='.config('identity_card.photo.min_height')];
        }
        if ($step === 5) {
            $rules += self::witnessRules($complete);
        }

        return $rules;
    }

    public function attributes(): array
    {
        $labels = [];
        foreach (config('identity_card.fields') as $fields) {
            foreach ($fields as $key => $field) {
                $labels['data.'.$key] = $field['label'];
            }
        }

        return $labels;
    }

    public function messages(): array
    {
        return self::validationMessages();
    }

    public static function validationMessages(): array
    {
        return ['*.required' => 'هذا الحقل مطلوب.', '*.max' => 'القيمة تتجاوز الحد المسموح.', '*.date_format' => 'أدخل تاريخًا صحيحًا.', '*.before_or_equal' => 'لا يمكن أن يكون التاريخ في المستقبل.', '*.regex' => 'أدخل رقم هاتف صحيحًا من 7 إلى 15 رقمًا.', '*.in' => 'اختر قيمة صحيحة.', 'witnesses.1.document_number.different' => 'يجب أن يكون الشاهد الثاني مختلفًا عن الشاهد الأول.', 'photo.image' => 'ارفع صورة سليمة بصيغة JPG أو JPEG أو PNG.', 'photo.mimes' => 'الصيغ المسموحة JPG وJPEG وPNG فقط.', 'photo.extensions' => 'امتداد الصورة غير مسموح.', 'photo.max' => 'حجم الصورة الأقصى 2 ميجابايت وفق الإعدادات الحالية.', 'photo.dimensions' => 'أبعاد الصورة أقل من الحد المطلوب الموضح في الحقل.', 'declaration.accepted' => 'يجب الإقرار بصحة البيانات قبل الإرسال.'];
    }
}
