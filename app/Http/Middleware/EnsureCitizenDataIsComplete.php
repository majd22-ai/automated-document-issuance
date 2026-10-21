<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCitizenDataIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->missingCitizenFields() !== []) {
            return redirect()->route('citizen.data')->with('citizen_data_error', 'بياناتك غير مكتملة. استكمل بيانات الاتصال من الملف الشخصي، وراجع الجهة المختصة لتصحيح البيانات الرسمية قبل بدء الخدمة.');
        }

        return $next($request);
    }
}
