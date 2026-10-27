<?php

namespace App\Services\Registration;

use App\Models\User;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocalPasswordRecovery
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('auth.local_password_recovery') === true;
    }

    public function pending(Request $request): ?array
    {
        $state = $request->session()->get('local_recovery');
        if (! self::enabled() || ! is_array($state) || ($state['expires'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(['local_recovery', 'local_recovery_preview']);

            return null;
        }

        return $state;
    }

    public function start(Request $request, string $email): ?array
    {
        abort_unless(self::enabled(), 404);
        $request->session()->forget(['local_recovery', 'local_recovery_preview', 'local_recovery_password']);
        $user = User::where('email', $email)->where('is_active', true)->first();
        if (! $user) {
            return null;
        }
        $broker = Password::broker();
        if ($broker->getRepository()->recentlyCreatedToken($user)) {
            throw ValidationException::withMessages(['email' => 'انتظر دقيقة قبل طلب رمز استعادة آخر.']);
        }
        $token = $broker->createToken($user);
        $code = (string) random_int(100000, 999999);
        $request->session()->put('local_recovery', [
            'user_id' => $user->id, 'email' => $user->email, 'auth_version' => (int) $user->auth_version,
            'token' => Crypt::encryptString($token), 'code_hash' => Hash::make($code),
            'expires' => now()->addMinutes(10)->timestamp, 'attempts' => 0, 'verified' => false,
        ]);

        return ['email' => $user->email, 'code' => $code];
    }

    private function requiredState(Request $request): array
    {
        abort_unless(self::enabled(), 404);
        $state = $this->pending($request);
        if (! $state) {
            throw ValidationException::withMessages(['recovery' => 'انتهت محاولة الاستعادة. أدخل بريدك واطلب رمزًا جديدًا.']);
        }

        return $state;
    }

    private function subject(array $state): User
    {
        $user = User::whereKey($state['user_id'])->where('email', $state['email'])->where('is_active', true)->first();
        if (! $user || (int) $user->auth_version !== $state['auth_version']) {
            throw ValidationException::withMessages(['recovery' => 'تغيرت حالة الحساب. ابدأ محاولة استعادة جديدة.']);
        }

        return $user;
    }

    public function verify(Request $request, string $code): void
    {
        $state = $this->requiredState($request);
        $this->subject($state);
        $state['attempts']++;
        if ($state['attempts'] > 5 || ! Hash::check($code, $state['code_hash'])) {
            if ($state['attempts'] >= 5) {
                $request->session()->forget(['local_recovery', 'local_recovery_preview']);
            } else {
                $request->session()->put('local_recovery', $state);
            }
            throw ValidationException::withMessages(['code' => $state['attempts'] >= 5 ? 'نفدت المحاولات. اطلب رمزًا جديدًا.' : 'رمز الاستعادة غير صحيح.']);
        }
        $state['verified'] = true;
        $request->session()->put('local_recovery', $state);
        $request->session()->forget('local_recovery_preview');
    }

    public function complete(Request $request, array $data): void
    {
        $state = $this->requiredState($request);
        if (! $state['verified']) {
            throw ValidationException::withMessages(['recovery' => 'تحقق من رمز الاستعادة قبل تعيين كلمة المرور.']);
        }

        DB::transaction(function () use ($state, $data) {
            $user = User::whereKey($state['user_id'])->lockForUpdate()->firstOrFail();
            $this->subject($state);
            $status = Password::broker()->reset([
                'email' => $state['email'], 'token' => Crypt::decryptString($state['token']),
                'password' => $data['password'], 'password_confirmation' => $data['password_confirmation'],
            ], function (User $account, string $password) use ($user) {
                abort_unless($account->id === $user->id, 422);
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                app(AuditTrail::class)->record('account.local_password_reset', 'user', $user->id, result: 'success');
            });
            if ($status !== Password::PASSWORD_RESET) {
                throw ValidationException::withMessages(['recovery' => 'انتهت صلاحية الاستعادة. اطلب رمزًا جديدًا.']);
            }
        }, 3);
        $request->session()->forget(['local_recovery', 'local_recovery_preview', 'local_recovery_password']);
        $request->session()->regenerate(true);
    }
}
