<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ($current = $request->user()->fresh())) {
            Auth::setUser($current);
        }
        if ($request->user() && Auth::viaRemember()) {
            $request->session()->put('auth_version', (int) $request->user()->auth_version);
        }
        $legacySession = $request->user() && $request->session()->has(Auth::guard()->getName()) && ! $request->session()->has('auth_version');
        if ($request->user() && (! $request->user()->is_active || $legacySession || ($request->session()->has('auth_version') && (int) $request->session()->get('auth_version') !== (int) $request->user()->auth_version))) {
            $message = $request->user()->is_active ? 'انتهت صلاحية جلسة الدخول. سجّل الدخول مجددًا.' : 'الحساب معطّل. يرجى التواصل مع الإدارة.';
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['identifier' => $message]);
        }

        return $next($request);
    }
}
