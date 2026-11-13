<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatePostalCollector
{
    public function handle(Request $request, Closure $next)
    {
        $guard = Auth::guard('postal');
        $collector = $guard->user()?->fresh();
        if (! $collector || ! $collector->canAccessPostalWorkspace()
            || $request->session()->get('postal_auth_version') !== $collector->auth_version) {
            $guard->logout();
            $request->session()->forget('postal_auth_version');

            return redirect()->route('postal.login');
        }
        $guard->setUser($collector);

        return $next($request);
    }
}
