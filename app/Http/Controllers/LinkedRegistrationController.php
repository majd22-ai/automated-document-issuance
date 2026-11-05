<?php

namespace App\Http\Controllers;

use App\Services\Registration\LinkedRegistration;
use App\Services\Registration\MockCivilSystems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class LinkedRegistrationController extends Controller
{
    public function show(Request $r, int $step = 1)
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        $pending = app(LinkedRegistration::class)->pending($r);
        $payload = $pending ? json_decode(Crypt::decryptString($pending->payload), true) : [];
        if ($step > 1 && (! $pending || ($payload['workflow'] ?? null) !== 'linked_mock')) {
            return redirect()->route('register');
        }
        if ($step === 3 && empty($payload['verified'])) {
            return redirect()->route('register.verify');
        }

        return response()->view('auth.linked-register', compact('step', 'payload'))->header('Cache-Control', 'no-store, private');
    }

    public function start(Request $r)
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        $data = $r->validate([
            'identity_path' => 'required|in:has_identity,no_identity', 'name' => 'required|string|max:100',
            'national_id' => 'nullable|required_if:identity_path,has_identity|prohibited_if:identity_path,no_identity|regex:/^[0-9]{5,20}$/D|unique:users,national_id',
            'email' => 'required|email|max:255|unique:users,email', 'phone' => ['required', 'regex:/^7[0-9]{8}$/D'],
            'birth_date' => 'nullable|required_if:identity_path,no_identity|date|before_or_equal:today',
            'birth_place' => 'nullable|required_if:identity_path,no_identity|string|max:150',
            'father_name' => 'nullable|required_if:identity_path,no_identity|string|max:100', 'mother_name' => 'nullable|required_if:identity_path,no_identity|string|max:100',
            'father_national_id' => 'nullable|regex:/^[0-9]{5,20}$/D', 'mother_national_id' => 'nullable|regex:/^[0-9]{5,20}$/D',
            'family_reference' => 'nullable|string|max:100', 'additional_information' => 'nullable|string|max:1000',
            'birth_certificate' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', 'terms' => 'accepted',
            'role' => 'missing', 'is_active' => 'missing', 'registration_status' => 'missing', 'mock_person_id' => 'missing', 'digital_account_id' => 'missing',
        ], ['*.required' => 'هذا الحقل مطلوب.', '*.required_if' => 'هذا الحقل مطلوب لمسار التسجيل المختار.', '*.unique' => 'القيمة مرتبطة بحساب قائم.', '*.email' => 'أدخل بريدًا إلكترونيًا صحيحًا.', '*.regex' => 'تنسيق البيانات غير صحيح.', '*.missing' => 'لا يمكن تعيين الصلاحيات أو نتائج التحقق من النموذج.', 'terms.accepted' => 'وافق على الشروط لإكمال التسجيل.']);
        $path = null;
        try {
            if ($r->hasFile('birth_certificate')) {
                $path = $r->file('birth_certificate')->store('mock-registration-evidence', 'attachments');
            }
            unset($data['birth_certificate']);
            if ($path) {
                $data['birth_certificate_path'] = $path;
            }
            app(LinkedRegistration::class)->start($r, $data);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('attachments')->delete($path);
            }
            throw $e;
        }

        return redirect()->route('register.verify');
    }

    public function verify(Request $r)
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        $data = $r->validate(['code' => ['required', 'regex:/^[0-9]{6}$/D']], ['code.required' => 'أدخل الرمز.', 'code.regex' => 'أدخل ستة أرقام.']);
        app(LinkedRegistration::class)->verify($r, $data['code']);

        return redirect()->route('register.password');
    }

    public function password(Request $r)
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        if ($r->isMethod('get')) {
            return $this->show($r, 3);
        }
        $data = $r->validate(['password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()->symbols()], 'username' => ['nullable', 'string', 'min:4', 'max:30', 'regex:/^[A-Za-z0-9_]+$/D', 'unique:users,username', 'unique:users,national_id']], ['password.confirmed' => 'كلمتا المرور غير متطابقتين.', 'password.min' => 'كلمة المرور لا تقل عن 8 أحرف.', 'username.unique' => 'اسم المستخدم مستخدم.']);
        $user = app(LinkedRegistration::class)->finish($r, $data);

        return redirect()->route('login')->with('status', 'تم إنشاء حسابك بنجاح. يرجى استكمال إجراءات تفعيل الحساب للتحقق من هويتك والاستفادة من الخدمات الإلكترونية.')->with('mock_account_id', $user->digital_account_id);
    }
}
