<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveIdentityCardForm extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ((int) $this->input('step') !== 2) {
            return;
        }
        $data = $this->input('data', []);
        if (! is_array($data)) {
            return;
        }
        foreach (['birth_gregorian', 'birth_hijri'] as $field) {
            $parts = $this->input('birth_dates.'.$field);
            if (! is_array($parts)) {
                continue;
            }
            $values = array_map(fn ($part) => $parts[$part] ?? '', ['year', 'month', 'day']);
            if (count(array_filter($values, fn ($value) => $value !== '' && $value !== null)) === 0) {
                // Preserve only legacy written dates which cannot be represented by these selectors.
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($data[$field] ?? ''))) {
                    $data[$field] = null;
                }

                continue;
            }
            $validParts = count(array_filter($values, fn ($v) => is_scalar($v) && ctype_digit((string) $v))) === 3;
            [$year, $month, $day] = array_map(fn ($v) => is_scalar($v) ? (int) $v : 0, $values);
            $valid = $validParts && ($field === 'birth_gregorian' ? checkdate($month, $day, $year) : ($year >= 1 && $year <= 1500 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 30));
            $data[$field] = $valid ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
            if (! $valid) {
                $this->merge(['invalid_birth_date_'.$field => true]);
            }
        }
        $this->merge(['data' => $data]);
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (['birth_gregorian', 'birth_hijri'] as $field) {
                if ($this->input('invalid_birth_date_'.$field)) {
                    $validator->errors()->add('data.'.$field, 'اختر يومًا وشهرًا وسنة لتاريخ صحيح.');
                }
            }
        }];
    }

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
        foreach (['name', 'document_type', 'document_number', 'issued_place', 'phone', 'address', 'employer', 'work_address', 'nearby_landmark'] as $key) {
            $rules['witnesses.*.'.$key] = [$complete && ! in_array($key, ['employer', 'work_address', 'nearby_landmark']) ? 'required' : 'nullable', 'string', 'max:200'];
        }
        $rules['witnesses.*.issued_at'] = [$complete ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
        $rules['witnesses.*.phone'][] = 'regex:/^\+?[0-9]{7,15}$/';
        $rules['witnesses.1.document_number'][] = 'different:witnesses.0.document_number';
        $rules['witnesses.*'] = ['array:name,document_type,document_number,issued_place,issued_at,employer,work_address,phone,address,nearby_landmark,selfie'];
        $rules['witnesses.*.selfie'] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048', 'dimensions:min_width=600,min_height=600'];

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
        return [
            'witnesses.*.selfie.image' => 'ارفع صورة سلفي سليمة للشاهد وهو يحمل بطاقته.',
            'witnesses.*.selfie.mimes' => 'صورة الشاهد يجب أن تكون JPG أو JPEG أو PNG.',
            'witnesses.*.selfie.extensions' => 'امتداد صورة الشاهد يجب أن يكون JPG أو JPEG أو PNG.',
            'witnesses.*.selfie.max' => 'حجم صورة الشاهد يجب ألا يتجاوز 2 ميجابايت.',
            'witnesses.*.selfie.dimensions' => 'أبعاد صورة الشاهد يجب ألا تقل عن 600 × 600 بكسل.',
        ] + self::validationMessages();
    }

    public static function validationMessages(): array
    {
        $messages = [
            'data.*.required' => 'يرجى إدخال :attribute للانتقال إلى الخطوة التالية.',
            'data.*.string' => 'يرجى إدخال نص صحيح في حقل :attribute.',
            'data.*.max' => 'يجب ألا يتجاوز حقل :attribute 200 حرف.',
            'data.*.in' => 'يرجى اختيار قيمة صحيحة لحقل :attribute.',
            'data.*.date_format' => 'يرجى اختيار تاريخ صحيح لحقل :attribute.',
            'data.*.before_or_equal' => 'لا يمكن أن يكون :attribute في المستقبل.',
            'data.*.regex' => 'أدخل رقم هاتف صحيحًا من 7 إلى 15 رقمًا.',
        ];

        return $messages + ['*.required' => 'هذا الحقل مطلوب.', '*.max' => 'القيمة تتجاوز الحد المسموح.', '*.date_format' => 'أدخل تاريخًا صحيحًا.', '*.before_or_equal' => 'لا يمكن أن يكون التاريخ في المستقبل.', '*.regex' => 'أدخل رقم هاتف صحيحًا من 7 إلى 15 رقمًا.', '*.in' => 'اختر قيمة صحيحة.', 'witnesses.1.document_number.different' => 'يجب أن يكون الشاهد الثاني مختلفًا عن الشاهد الأول.', 'photo.image' => 'ارفع صورة سليمة بصيغة JPG أو JPEG أو PNG.', 'photo.mimes' => 'الصيغ المسموحة JPG وJPEG وPNG فقط.', 'photo.extensions' => 'امتداد الصورة غير مسموح.', 'photo.max' => 'حجم الصورة الأقصى 2 ميجابايت وفق الإعدادات الحالية.', 'photo.dimensions' => 'أبعاد الصورة أقل من الحد المطلوب الموضح في الحقل.', 'declaration.accepted' => 'يجب الإقرار بصحة البيانات قبل الإرسال.'];
    }
}
