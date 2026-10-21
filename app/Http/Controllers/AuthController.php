<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'national_id' => ['required', 'string', 'max:20', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{7,15}$/'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'role' => ['missing'], 'is_active' => ['missing'],
        ], [
            'name.required' => 'الاسم الكامل مطلوب.',
            'national_id.required' => 'الرقم الوطني مطلوب.',
            'national_id.unique' => 'هذا الرقم الوطني مسجّل مسبقًا.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'أدخل بريدًا إلكترونيًا صحيحًا.',
            'email.unique' => 'هذا البريد الإلكتروني مسجّل مسبقًا.',
            'phone.regex' => 'أدخل رقم هاتف صحيحًا من 7 إلى 15 رقمًا، مع رمز الدولة اختياريًا.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'role.prohibited' => 'لا يمكن اختيار الدور من نموذج التسجيل.',
            'is_active.prohibited' => 'لا يمكن تحديد حالة الحساب من نموذج التسجيل.',
        ]);

        $user = new User([
            'name' => $validated['name'], 'username' => $validated['national_id'],
            'email' => $validated['email'], 'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);
        $user->forceFill(['role' => 'citizen', 'is_active' => true])->save();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('citizen.dashboard');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ], [
            'identifier.required' => 'أدخل البريد الإلكتروني أو الرقم الوطني أو اسم المستخدم.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);
        $field = filter_var($validated['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([
            $field => $validated['identifier'], 'password' => $validated['password'], 'is_active' => true,
        ], $request->boolean('remember'))) {
            return back()->withErrors(['identifier' => 'بيانات الدخول غير صحيحة أو الحساب غير مفعّل.'])
                ->withInput($request->only('identifier', 'remember'));
        }

        $request->session()->regenerate();
        $role = $request->user()->role;
        if (! in_array($role, User::ROLES, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['identifier' => 'هذا الحساب لا يملك دورًا صالحًا. راجع الإدارة.']);
        }
        $intended = $request->session()->pull('url.intended');
        $target = $validated['redirect'] ?? $intended;
        $origin = $request->getSchemeAndHttpHost();
        if (is_string($target) && str_starts_with($target, $origin.'/')) {
            $target = substr($target, strlen($origin));
        }

        if (is_string($target) && $this->safeDestination($target, $role)) {
            return redirect($target);
        }

        return redirect()->route($role.'.dashboard');
    }

    private function safeDestination(string $target, string $role): bool
    {
        $decoded = rawurldecode($target);
        if (! str_starts_with($decoded, '/') || str_starts_with($decoded, '//') || preg_match('/[\\\\\x00-\x20]/', $decoded) || str_contains($decoded, '..')) {
            return false;
        }
        $path = parse_url($decoded, PHP_URL_PATH);

        return $path === '/profile' || (is_string($path) && str_starts_with($path, '/'.$role.'/'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
