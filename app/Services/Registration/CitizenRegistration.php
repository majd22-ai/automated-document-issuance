<?php

namespace App\Services\Registration;

use App\Contracts\RegistrationSms;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitizenRegistration
{
    public function __construct(private RegistrationSms $sms) {}

    private function key(Request $request): string
    {
        return hash('sha256', (string) $request->session()->get('registration_token', ''));
    }

    public function pending(Request $request): ?object
    {
        return DB::table('registration_attempts')->where('session_key', $this->key($request))->where('expires_at', '>', now())->first();
    }

    public function start(Request $request, array $data): void
    {
        $this->cancel($request);
        $phone = '+967'.$data['phone'];
        $code = (string) random_int(100000, 999999);
        $token = Str::random(64);
        $payload = array_intersect_key($data, array_flip(['name', 'username', 'email', 'national_id', 'identity_path']));
        $payload += ['phone' => $phone, 'password' => Hash::make($data['password'])];
        $payload['local_otp'] = $this->sms instanceof LocalSms;
        $this->send($phone, $code);
        DB::table('registration_attempts')->insert([
            'session_key' => hash('sha256', $token), 'payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'otp_hash' => Hash::make($code), 'attempts' => 0, 'sends' => 1, 'sent_at' => now(),
            'expires_at' => now()->addMinutes(config('registration.otp_minutes')), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $request->session()->put('registration_token', $token);
        $request->session()->forget('registration_captcha');
    }

    private function send(string $phone, string $code): void
    {
        // Shared limits across registration sessions; keys never contain a phone number.
        $key = 'registration-sms:'.hash_hmac('sha256', $phone, config('app.key'));
        $lock = Cache::lock($key.':lock', 20);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['phone' => 'يجري إرسال رمز لهذا الرقم. انتظر قبل المحاولة مجددًا.']);
        }
        try {
            if (RateLimiter::tooManyAttempts($key, 1) || RateLimiter::tooManyAttempts($key.':hour', 5)) {
                throw ValidationException::withMessages(['phone' => 'انتظر قبل طلب رمز آخر لهذا الرقم.']);
            }
            RateLimiter::hit($key, config('registration.resend_seconds'));
            RateLimiter::hit($key.':hour', 3600);
            try {
                $this->sms->send($phone, $code);
            } catch (\Throwable) {
                // No exception payload/provider response is logged: these may contain OTP or PII.
                throw ValidationException::withMessages(['phone' => 'تعذّر إرسال رمز التحقق. خدمة الرسائل غير متاحة أو لم تُربط بعد؛ لم يكتمل التسجيل.']);
            }
        } finally {
            $lock->release();
        }
    }

    public function resend(Request $request): void
    {
        $result = DB::transaction(function () use ($request) {
            $row = DB::table('registration_attempts')->where('session_key', $this->key($request))->lockForUpdate()->first();
            if (! $row || now()->gte($row->expires_at) || $row->sends >= config('registration.max_sends') || now()->lt(Carbon::parse($row->sent_at)->addSeconds(config('registration.resend_seconds')))) {
                throw ValidationException::withMessages(['code' => 'انتظر موعد إعادة الإرسال، أو ابدأ التسجيل مجددًا إذا انتهت المحاولة.']);
            }
            $payload = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);
            do {
                $code = (string) random_int(100000, 999999);
            } while (Hash::check($code, $row->otp_hash));
            try {
                $this->send($payload['phone'], $code);
            } catch (ValidationException $exception) {
                // Commit database-backed limiter hits even when delivery fails/has an unknown outcome.
                return $exception;
            }
            DB::table('registration_attempts')->where('id', $row->id)->update(['otp_hash' => Hash::make($code), 'sends' => $row->sends + 1, 'sent_at' => now(), 'updated_at' => now()]);
        });
        if ($result instanceof ValidationException) {
            throw $result;
        }
    }

    public function verify(Request $request, string $code): User
    {
        try {
            $result = app(CitizenIdentifiers::class)->serialized(fn () => DB::transaction(function () use ($request, $code) {
                $row = DB::table('registration_attempts')->where('session_key', $this->key($request))->lockForUpdate()->first();
                if (! $row || now()->gte($row->expires_at) || $row->attempts >= config('registration.max_attempts')) {
                    return null;
                }
                DB::table('registration_attempts')->where('id', $row->id)->increment('attempts');
                if (! Hash::check($code, $row->otp_hash)) {
                    return null; // Commit attempt count even when verification fails.
                }
                $data = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);
                // Recheck legacy identity conflict under verification; DB unique constraints cover new accounts.
                if (app(CitizenIdentifiers::class)->conflicts($data['username']) || (filled($data['national_id'] ?? null) && app(CitizenIdentifiers::class)->conflicts($data['national_id']))) {
                    throw ValidationException::withMessages(['code' => 'البيانات مستخدمة بالفعل. عد إلى بيانات المستخدم.']);
                }
                $localOtp = (bool) ($data['local_otp'] ?? false);
                unset($data['local_otp']);
                if ($localOtp && ! LocalSms::enabled()) {
                    return null;
                }
                $user = new User;
                $user->forceFill($data + ['role' => 'citizen', 'is_active' => true, 'phone_verified_at' => $localOtp ? null : now(), 'registration_status' => $data['identity_path'] === 'no_identity' ? 'initial_account' : 'identity_verification_pending']);
                $user->save();
                DB::table('registration_attempts')->where('id', $row->id)->delete();

                return $user;
            }));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'البريد أو اسم المستخدم أو رقم الهوية أصبح مستخدمًا. عد إلى بيانات المستخدم.']);
        }
        if (! $result) {
            throw ValidationException::withMessages(['code' => 'الرمز غير صحيح أو انتهت صلاحيته أو استُنفدت محاولاته.']);
        }
        $request->session()->forget(['registration_token', 'registration_form', 'registration_captcha', 'registration_local_code']);

        return $result;
    }

    public function cancel(Request $request): void
    {
        DB::table('registration_attempts')->where('session_key', $this->key($request))->delete();
        $request->session()->forget(['registration_token', 'registration_local_code']);
    }
}
