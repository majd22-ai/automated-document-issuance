<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ], [
            'identifier.required' => 'أدخل الرقم الوطني أو اسم المستخدم.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);
        $field = filter_var($validated['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $accounts = User::where(fn ($query) => $field === 'email'
            ? $query->where('email', $validated['identifier'])
            : $query->where('username', $validated['identifier'])->orWhere('national_id', $validated['identifier']))->limit(2)->get(['id']);
        if ($accounts->count() !== 1) {
            event(new Failed(config('auth.defaults.guard'), null, []));
        }
        if ($accounts->count() !== 1 || ! Auth::attempt([
            'id' => $accounts->first()->id, 'password' => $validated['password'], 'is_active' => true,
        ], $request->boolean('remember'))) {
            return back()->withErrors(['identifier' => 'بيانات الدخول غير صحيحة أو الحساب غير متاح.'])
                ->withInput($request->only('identifier', 'remember'));
        }

        $request->session()->regenerate();
        if ($request->user()->needsCitizenActivation()) {
            $request->session()->forget('url.intended');

            return redirect()->route('citizen.account.status');
        }
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
