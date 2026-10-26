<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class EmployeePasswordController extends Controller
{
    public function form(Request $request, string $token)
    {
        return response()->view('auth.employee-reset', ['token' => $token, 'email' => $request->query('email')])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed']]);
        $status = Password::reset($data + ['password_confirmation' => $request->input('password_confirmation')], function (User $user, string $password) {
            abort_unless(in_array($user->role, ['employee', 'admin']) && $user->is_active, 422);
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'auth_version' => $user->auth_version + 1])->save();
                app(AuditTrail::class)->record('employee.password_reset', 'user', $user->id, $user);
            });
        });

        return $status === Password::PASSWORD_RESET ? redirect()->route('login')->with('status', 'تم تغيير كلمة المرور.') : back()->withErrors(['email' => 'الرابط غير صالح أو منتهي الصلاحية.']);
    }
}
