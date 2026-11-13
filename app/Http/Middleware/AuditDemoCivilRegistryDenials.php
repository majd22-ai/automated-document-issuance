<?php

namespace App\Http\Middleware;

use App\Services\DemoCivilRegistry\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditDemoCivilRegistryDenials
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('employee/demo-civil-registry', 'employee/demo-civil-registry/*', 'admin/demo-civil-registry', 'admin/demo-civil-registry/*')) {
            return $next($request);
        }
        $audit = app(AuditLogger::class);
        if ($request->user() === null) {
            try {
                $audit->record($request, $request->route()?->getName() ?? 'unauthenticated', 'denied');
            } catch (Throwable) {
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)->header('Cache-Control', 'private, no-store')
                : redirect()->guest(route('login'));
        }
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                $audit->record($request, $request->route()?->getName() ?? 'unmatched', 'denied');
            }

            return $response;
        } catch (Throwable $exception) {
            try {
                $audit->record($request, $request->route()?->getName() ?? 'unmatched', 'denied');
            } catch (Throwable) {
            }

            throw $exception;
        }
    }
}