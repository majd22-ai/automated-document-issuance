<?php

namespace App\Http\Middleware;

use App\Models\NeighborhoodOfficial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403);
        if (NeighborhoodOfficial::residenceOnly($request->user()) && $request->routeIs('employee.*')) {
            abort_unless($request->routeIs('employee.dashboard', 'employee.notifications.*', 'employee.neighborhood-attestations.*'), 403);
        }

        return $next($request);
    }
}
