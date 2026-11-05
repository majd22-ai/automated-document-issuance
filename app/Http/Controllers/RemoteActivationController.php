<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Registration\RemoteActivation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RemoteActivationController extends Controller
{
    public function send(Request $request, RemoteActivation $flow)
    {
        $request->merge(['phone' => preg_match('/^7[0-9]{8}$/D', (string) $request->phone) ? '+967'.$request->phone : $request->phone]);
        $data = $request->validate(['national_id' => ['required', 'string', 'regex:/^[0-9]{5,20}$/D'], 'phone' => ['required', 'string', 'regex:/^\+9677[0-9]{8}$/D'], 'phone_change_confirmed' => ['accepted']], ['*.required' => 'هذا الحقل مطلوب.', 'phone.regex' => 'أدخل رقم جوال يمني صحيحًا.', 'national_id.regex' => 'أدخل الرقم الوطني من 5 إلى 20 رقمًا.', 'phone_change_confirmed.accepted' => 'أكد استخدام هذا الرقم وتحديث رقم حسابك عند اعتماد التفعيل.']);
        $code = $flow->send($request->user(), $data);

        return redirect()->route('citizen.account.status')->with('local_activation_code', $code)->with('status', $code ? 'محاكاة محلية: لم يُرسل الرمز إلى شبكة الهاتف.' : 'أُرسل رمز التحقق عبر مزود الرسائل.');
    }

    public function verify(Request $request, RemoteActivation $flow)
    {
        $data = $request->validate(['otp' => ['required', 'string', 'regex:/^[0-9]{6}$/D']], ['otp.regex' => 'أدخل ستة أرقام.', 'otp.required' => 'أدخل رمز التحقق.']);
        $flow->verify($request->user(), $data['otp']);

        return redirect()->route('citizen.account.status')->with('status', 'نجح التحقق من الهاتف. ارفع وثائق الهوية والسيلفي الآن.');
    }

    public function submit(Request $request, RemoteActivation $flow)
    {
        $rules = ['document_kind' => ['required', 'in:identity_card,birth_certificate'], 'no_physical_card' => ['required_if:document_kind,birth_certificate', 'accepted_if:document_kind,birth_certificate'],
            'selfie_capture_acknowledged' => ['accepted'], 'declaration' => ['accepted']];
        foreach (['front', 'back', 'selfie'] as $field) {
            $rules[$field] = [$field === 'back' ? 'required_if:document_kind,identity_card' : 'required', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:min_width=300,min_height=300'];
        }
        $data = $request->validate($rules, ['*.required' => 'هذا الحقل مطلوب.', '*.required_if' => 'هذا الملف مطلوب لنوع الوثيقة المختار.', '*.image' => 'ارفع صورة صالحة.', '*.mimes' => 'الصيغ المقبولة JPG وPNG.', '*.max' => 'حجم الصورة لا يتجاوز 5 ميجابايت.', '*.dimensions' => 'أبعاد الصورة لا تقل عن 300 × 300.', '*.accepted' => 'يلزم تأكيد هذا الإقرار.', '*.accepted_if' => 'أكد عدم توفر البطاقة الشخصية لاستخدام شهادة الميلاد.']);
        $flow->submit($request->user(), $data);

        return redirect()->route('citizen.account.status')->with('status', 'أُرسل طلب التفعيل الإلكتروني إلى الموظف للمراجعة دون اشتراط الحضور.');
    }

    public function image(Request $request, User $citizen, string $field)
    {
        if ($request->user()->id !== $citizen->id) {
            Gate::authorize('activateCitizen', $citizen);
        } else {
            abort_unless($request->user()->role === 'citizen' && $request->user()->is_active, 403);
        }
        abort_unless(in_array($field, ['front', 'back', 'selfie'], true), 404);
        $row = $citizen->activationRequest;
        abort_unless($row?->workflow === 'remote', 404);
        $path = data_get($row->evidence, $field.'.path');
        abort_unless($path && Storage::disk('attachments')->exists($path), 404);

        return Storage::disk('attachments')->response($path, null, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    public function review(Request $request, User $citizen, RemoteActivation $flow)
    {
        Gate::authorize('activateCitizen', $citizen);
        $data = $request->validate(['decision' => ['required', 'in:rejected,requires_correction'], 'reason' => ['required', 'string', 'min:5', 'max:1500'], 'revision' => ['required', 'integer', 'min:1']], ['reason.required' => 'أدخل سبب الرفض أو المتطلبات المطلوب استكمالها.']);
        $flow->review($request->user(), $citizen, $data);

        return back()->with('status', 'تم حفظ القرار وإشعار المواطن.');
    }
}
