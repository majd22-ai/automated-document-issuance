<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePostalNetworkEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->environment(['local', 'testing']) && config('postal_network.enabled'), 404);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
