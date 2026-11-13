<?php

namespace App\Http\Controllers;

use App\Rules\PostalPasswordLength;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PostalAuthController extends Controller
{
    public function loginForm()
    {
        return view('postal.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['username' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:72', new PostalPasswordLength]]);
        $guard = Auth::guard('postal');
        if (! $guard->attemptWhen($credentials, fn ($collector) => $collector->canAccessPostalWorkspace())) {
            throw ValidationException::withMessages(['username' => 'تعذر تسجيل الدخول. تحقق من البيانات وتفعيل الحساب والمكتب.']);
        }
        $request->session()->regenerate();
        $request->session()->put('postal_auth_version', $guard->user()->auth_version);

        return redirect()->route('postal.home');
    }

    public function home()
    {
        return view('postal.home', ['collector' => Auth::guard('postal')->user()]);
    }

    public function logout(Request $request)
    {
        Auth::guard('postal')->logout();
        $request->session()->forget('postal_auth_version');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('postal.login');
    }
}
