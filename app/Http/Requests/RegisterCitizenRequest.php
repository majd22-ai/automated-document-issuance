<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCitizenRequest extends FormRequest
{
    public static function captchaMatches(Request $request, mixed $value): bool
    {
        $challenge = $request->session()->get('registration_captcha');

        return is_string($value) && strlen($value) === 6 && $challenge && $challenge['expires'] > now()->timestamp && Hash::check(strtoupper($value), $challenge['hash']);
    }

    public function authorize(): bool
    {
        return ! $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'identity_path' => ['required', Rule::in(['has_identity', 'no_identity', 'existing_record'])],
            'national_id' => ['nullable', 'required_unless:identity_path,no_identity', 'prohibited_if:identity_path,no_identity', 'regex:/^[0-9]{5,20}$/', 'unique:users,national_id', function ($attribute, $value, $fail) {
                if (User::where('username', $value)->exists()) {
                    $fail('رقم الهوية مستخدم بالفعل.');
                }
            }],
            'username' => ['required', 'string', 'min:4', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username', 'unique:users,national_id'],
            'birth_certificate_number' => ['nullable', 'required_if:identity_path,no_identity', 'prohibited_unless:identity_path,no_identity', 'regex:/^[0-9]{1,50}$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^7[0-9]{8}$/'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()->symbols(), 'regex:/[\p{S}\p{P}]/u'],
            'terms' => ['accepted'],
            'captcha' => ['required', 'string', 'max:10', function ($attribute, $value, $fail) {
                if (! self::captchaMatches($this, $value)) {
                    $fail('رمز الصورة غير صحيح أو انتهت صلاحيته. حدّث الصورة وحاول مجددًا.');
                }
            }],
            'role' => ['missing'], 'is_active' => ['missing'], 'registration_status' => ['missing'],
            'phone_verified_at' => ['missing'], 'email_verified_at' => ['missing'], 'id' => ['missing'],
            'account_activated_at' => ['missing'], 'identity_verified_at' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'هذا الحقل مطلوب.', '*.unique' => 'القيمة مستخدمة بالفعل.',
            'national_id.required_unless' => 'أدخل رقم الهوية المرتبط بسجلك المدني.',
            'birth_certificate_number.required_if' => 'أدخل رقم شهادة الميلاد الخاص بك.',
            'birth_certificate_number.regex' => 'أدخل رقم شهادة الميلاد باستخدام الأرقام فقط، بحد أقصى 50 رقمًا.',
            'birth_certificate_number.prohibited_unless' => 'اختر مسار عدم امتلاك رقم هوية لإدخال رقم شهادة الميلاد.',
            'national_id.prohibited_if' => 'لا يجوز إدخال رقم هوية لمسار الحساب دون هوية.',
            'national_id.regex' => 'أدخل رقم هوية من 5 إلى 20 رقمًا؛ يلزم التحقق الرسمي منه لاحقًا.',
            'username.regex' => 'استخدم حروفًا إنجليزية وأرقامًا والشرطة السفلية فقط.',
            'username.min' => 'اسم المستخدم لا يقل عن 4 خانات.',
            'phone.regex' => 'أدخل رقم جوال يمني من 9 أرقام يبدأ بالرقم 7 دون رمز الدولة.',
            'email.email' => 'أدخل بريدًا إلكترونيًا صحيحًا.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين',
            'password.min' => 'كلمة المرور لا تقل عن 8 خانات.',
            'password.letters' => 'يجب أن تتضمن كلمة المرور حروفًا.',
            'password.numbers' => 'يجب أن تتضمن كلمة المرور أرقامًا.',
            'password.symbols' => 'يجب أن تتضمن كلمة المرور رموزًا.',
            'password.regex' => 'أضف رمزًا أو علامة ترقيم إلى كلمة المرور؛ المسافة وحدها ليست رمزًا.',
            'terms.accepted' => 'يجب الموافقة على شروط الاستخدام.',
            '*.missing' => 'لا يُسمح بتعيين الصلاحيات أو حالة الحساب من نموذج التسجيل.',
        ];
    }
}
