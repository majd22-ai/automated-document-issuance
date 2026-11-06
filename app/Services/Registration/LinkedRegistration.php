<?php

namespace App\Services\Registration;

use App\Models\User;
use App\Services\Oversight\AuditTrail;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinkedRegistration
{
    private function key(Request $r): string
    {
        return hash('sha256', (string) $r->session()->get('registration_token', ''));
    }

    public function pending(Request $r): ?object
    {
        return DB::table('registration_attempts')->where('session_key', $this->key($r))->where('expires_at', '>', now())->first();
    }

    public function start(Request $r, array $data): void
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        $result = app(MockCivilSystems::class)->lookup($data);
        if ($result['person_id'] && User::where('mock_person_id', $result['person_id'])->exists()) {
            throw ValidationException::withMessages(['national_id' => 'يوجد حساب مرتبط بهذا السجل؛ استخدم استعادة كلمة المرور.']);
        }
        $this->limit($data['phone']);
        $code = (string) random_int(100000, 999999);
        $token = Str::random(64);
        $payload = $data + $result + ['workflow' => 'linked_mock', 'verified' => false];
        $payload['phone'] = '+967'.$data['phone'];
        DB::transaction(function () use ($r, $payload, $code, $token) {
            DB::table('registration_attempts')->where('session_key', $this->key($r))->delete();
            DB::table('registration_attempts')->insert(['session_key' => hash('sha256', $token), 'payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), 'otp_hash' => Hash::make($code), 'attempts' => 0, 'sends' => 1, 'sent_at' => now(), 'expires_at' => now()->addMinutes(3), 'created_at' => now(), 'updated_at' => now()]);
        });
        $r->session()->put('registration_token', $token);
        $r->session()->flash('mock_registration_code', Crypt::encryptString($code));
    }

    private function limit(string $phone): void
    {
        $key = 'linked-otp:'.hash_hmac('sha256', $phone, config('app.key'));
        if (RateLimiter::tooManyAttempts($key, 1) || RateLimiter::tooManyAttempts($key.':hour', 5)) {
            throw ValidationException::withMessages(['phone' => 'انتظر قبل طلب رمز جديد. الحد الأقصى خمسة طلبات في الساعة.']);
        }
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key.':hour', 3600);
    }

    public function verify(Request $r, string $code): void
    {
        $ok = DB::transaction(function () use ($r, $code) {
            $row = DB::table('registration_attempts')->where('session_key', $this->key($r))->lockForUpdate()->first();
            if (! $row || now()->gte($row->expires_at) || $row->attempts >= 5) {
                return false;
            }
            $payload = json_decode(Crypt::decryptString($row->payload), true);
            if (($payload['workflow'] ?? null) !== 'linked_mock' || $payload['verified']) {
                return false;
            }
            DB::table('registration_attempts')->where('id', $row->id)->increment('attempts');
            if (! Hash::check($code, $row->otp_hash)) {
                return false;
            }
            $payload['verified'] = true;
            DB::table('registration_attempts')->where('id', $row->id)->update(['payload' => Crypt::encryptString(json_encode($payload)), 'otp_hash' => Hash::make(Str::random(64)), 'expires_at' => now()->addMinutes(15), 'updated_at' => now()]);

            return true;
        });
        if (! $ok) {
            throw ValidationException::withMessages(['code' => 'الرمز غير صحيح أو منتهي أو مستخدم، أو استُنفدت المحاولات.']);
        }
        $r->session()->forget('mock_registration_code');
    }

    public function resend(Request $r): void
    {
        DB::transaction(function () use ($r) {
            $row = DB::table('registration_attempts')->where('session_key', $this->key($r))->lockForUpdate()->first();
            if (! $row || now()->gte($row->expires_at) || $row->sends >= 3 || now()->lt(Carbon::parse($row->sent_at)->addMinute())) {
                throw ValidationException::withMessages(['code' => 'انتظر دقيقة أو ابدأ التسجيل مجددًا عند انتهاء الجلسة.']);
            }
            $payload = json_decode(Crypt::decryptString($row->payload), true);
            if ($payload['verified']) {
                throw ValidationException::withMessages(['code' => 'تم التحقق بالفعل.']);
            }
            $this->limit(substr($payload['phone'], 4));
            do {
                $code = (string) random_int(100000, 999999);
            } while (Hash::check($code, $row->otp_hash));
            DB::table('registration_attempts')->where('id', $row->id)->update(['otp_hash' => Hash::make($code), 'attempts' => 0, 'sends' => $row->sends + 1, 'sent_at' => now(), 'expires_at' => now()->addMinutes(3), 'updated_at' => now()]);
            $r->session()->flash('mock_registration_code', Crypt::encryptString($code));
        });
    }

    public function finish(Request $r, array $password): User
    {
        try {
            $user = app(CitizenIdentifiers::class)->serialized(fn () => DB::transaction(function () use ($r, $password) {
                $row = DB::table('registration_attempts')->where('session_key', $this->key($r))->lockForUpdate()->first();
                if (! $row || now()->gte($row->expires_at)) {
                    throw ValidationException::withMessages(['password' => 'انتهت جلسة التسجيل.']);
                }
                $p = json_decode(Crypt::decryptString($row->payload), true);
                if (($p['workflow'] ?? null) !== 'linked_mock' || ! $p['verified']) {
                    abort(403);
                }
                $national = $p['identity_path'] === 'has_identity' ? $p['national_id'] : null;
                $account = $national ? (string) Str::uuid() : null;
                if ($national) {
                    $username = 'acct_'.str_replace('-', '', $account);
                } else {
                    $username = $password['username'] ?? '';
                    if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{3,19}$/D', $username)) {
                        throw ValidationException::withMessages(['username' => 'أدخل اسم مستخدم من 4 إلى 20 خانة يبدأ بحرف إنجليزي، ويحتوي على حروف إنجليزية وأرقام أو شرطة سفلية فقط.']);
                    }
                }
                if (app(CitizenIdentifiers::class)->conflicts($username) || ($national && app(CitizenIdentifiers::class)->conflicts($national))) {
                    throw ValidationException::withMessages(['username' => 'المعرف مستخدم بالفعل.']);
                }
                $user = (new User)->forceFill(['name' => $p['name'], 'national_id' => $national, 'username' => $username, 'digital_account_id' => $account, 'mock_person_id' => $p['person_id'], 'email' => $p['email'], 'phone' => $p['phone'], 'password' => Hash::make($password['password']), 'role' => 'citizen', 'is_active' => true, 'identity_path' => $p['identity_path'], 'registration_status' => $national ? 'pending_activation' : ($p['registry_result'] === 'MATCH' ? 'initial_account' : 'under_review'), 'activation_source' => 'linked_local_mock']);
                $user->save();
                if (! $national) {
                    $evidence = isset($p['birth_certificate_path']) ? ['birth' => ['path' => $p['birth_certificate_path']]] : [];
                    $evidence['registration'] = array_intersect_key($p, array_flip(['name', 'birth_date', 'birth_place', 'father_name', 'mother_name', 'father_national_id', 'mother_national_id', 'family_reference', 'additional_information']));
                    $row = app(MockIdentityVerification::class)->queue($user, $p['registry_result'], 'نتيجة بحث سجلات المواليد والأسرة: '.$p['registry_result'].'؛ لا يُعد البحث بالاسم إثباتًا للهوية.', $evidence, pending: $p['registry_result'] !== 'MATCH');
                }
                app(AuditTrail::class)->record('citizen_account.mock_registered', 'user', $user->id, $user, changes: ['status' => $user->registration_status]);
                DB::table('registration_attempts')->where('id', $row->id)->delete();

                return $user;
            }));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'البريد أو المعرف أو السجل مرتبط بحساب قائم؛ لم يُنشأ حساب مكرر.']);
        }
        $r->session()->forget(['registration_token', 'mock_registration_code']);

        return $user;
    }
}
