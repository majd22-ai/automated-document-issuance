<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterCitizenRequest;
use App\Services\Registration\CitizenRegistration;
use App\Services\Registration\LocalSms;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends Controller
{
    public function show(Request $request)
    {
        return response()->view('auth.register', ['step' => 1, 'form' => $request->session()->get('registration_form', [])])->header('Cache-Control', 'no-store, private');
    }

    public function store(RegisterCitizenRequest $request, CitizenRegistration $registration)
    {
        $data = $request->validated();
        $request->session()->put('registration_form', $request->safe()->except(['password', 'password_confirmation', 'captcha']));
        $registration->start($request, $data);

        return redirect()->route('register.verify');
    }

    public function verifyPage(Request $request, CitizenRegistration $registration)
    {
        $pending = $registration->pending($request);
        if (! $pending) {
            return redirect()->route('register')->with('status', 'انتهت جلسة التحقق. أدخل بيانات التسجيل مجددًا.');
        }
        $data = json_decode(Crypt::decryptString($pending->payload), true);

        return response()->view('auth.register', ['step' => 2, 'localCode' => LocalSms::enabled() ? $request->session()->get('registration_local_code') : null, 'maskedPhone' => substr($data['phone'], 0, 6).'••••'.substr($data['phone'], -2), 'resendAt' => Carbon::parse($pending->sent_at)->addSeconds(config('registration.resend_seconds'))->timestamp])->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request, CitizenRegistration $registration)
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/']], ['code.required' => 'أدخل رمز التحقق.', 'code.regex' => 'أدخل رمزًا من 6 أرقام.']);
        $user = $registration->verify($request, $data['code']);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('registration_completed', $user->id);

        return redirect()->route('register.complete');
    }

    public function resend(Request $request, CitizenRegistration $registration)
    {
        $registration->resend($request);

        return redirect()->route('register.verify')->with('status', 'تم إرسال رمز جديد وإبطال الرمز السابق.');
    }

    public function edit(Request $request, CitizenRegistration $registration)
    {
        $registration->cancel($request);

        return redirect()->route('register');
    }

    public function complete(Request $request)
    {
        if (! $request->user() || $request->session()->get('registration_completed') !== $request->user()->id) {
            return redirect()->route('register');
        }

        return response()->view('auth.register', ['step' => 3])->header('Cache-Control', 'no-store, private');
    }

    public function captcha(Request $request)
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $request->session()->put('registration_captcha', ['hash' => Hash::make($code), 'expires' => now()->addMinutes(5)->timestamp]);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="64" viewBox="0 0 220 64"><rect width="220" height="64" fill="#f1f5f9"/>';
        for ($i = 0; $i < 8; $i++) {
            $svg .= '<path d="M0 '.random_int(0, 64).' L220 '.random_int(0, 64).'" stroke="#94a3b8"/>';
        }
        for ($i = 0; $i < 6; $i++) {
            $svg .= '<text x="'.(15 + $i * 33).'" y="'.random_int(38, 51).'" font-family="monospace" font-size="29" font-weight="bold" fill="#123047" transform="rotate('.random_int(-12, 12).' '.(15 + $i * 33).' 32)">'.$code[$i].'</text>';
        }

        return response($svg.'</svg>', 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function checkCaptcha(Request $request)
    {
        return response()->json(['valid' => RegisterCitizenRequest::captchaMatches($request, $request->input('captcha'))])->header('Cache-Control', 'no-store, private');
    }
}
