<?php

namespace App\Http\Controllers;

use App\Services\Registration\LocalPasswordRecovery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class LocalPasswordRecoveryController extends Controller
{
    public function verify(Request $request, LocalPasswordRecovery $recovery)
    {
        abort_unless(LocalPasswordRecovery::enabled(), 404);
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']], ['code.required' => 'أدخل رمز الاستعادة.', 'code.regex' => 'الرمز مكوّن من 6 أرقام.']);
        $recovery->verify($request, $data['code']);

        return redirect()->route('password.request');
    }

    public function complete(Request $request, LocalPasswordRecovery $recovery)
    {
        abort_unless(LocalPasswordRecovery::enabled(), 404);
        $data = $request->validate(['password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()->symbols()], 'password_confirmation' => ['required', 'string']], [
            'password.required' => 'أدخل كلمة المرور الجديدة.', 'password.confirmed' => 'كلمة المرور وتأكيدها غير متطابقين.',
            'password.min' => 'كلمة المرور لا تقل عن 8 خانات.', 'password_confirmation.required' => 'أكد كلمة المرور الجديدة.',
            'password.letters' => 'أضف حروفًا إلى كلمة المرور.', 'password.numbers' => 'أضف أرقامًا إلى كلمة المرور.', 'password.symbols' => 'أضف رمزًا إلى كلمة المرور.',
        ]);
        $recovery->complete($request, $data);

        return redirect()->route('login')->with('status', 'تم تغيير كلمة المرور بنجاح. سجّل الدخول بكلمة المرور الجديدة.');
    }
}
