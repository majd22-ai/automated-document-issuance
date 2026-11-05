<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureAccountIsActive::class]);
        $middleware->alias(['role' => EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception, \Illuminate\Http\Request $request) {
            if ($exception->getStatusCode() === 419 && $request->isMethod('POST') && $request->is('login') && ! $request->expectsJson()) {
                return redirect()->route('login')->withErrors([
                    'identifier' => 'انتهت جلسة تسجيل الدخول. أعد إدخال بياناتك في النموذج الجديد ثم حاول مرة أخرى.',
                ])->header('Cache-Control', 'no-store, private');
            }

            return null;
        });
        $exceptions->dontFlash(['code', 'otp', 'captcha', 'evidence_reference', 'biometric_data', 'image', 'template']);
    })->create();
