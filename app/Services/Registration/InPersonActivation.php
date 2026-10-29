<?php

namespace App\Services\Registration;

use App\Contracts\RegistrationSms;
use App\Models\ActivationFingerprintAttempt;
use App\Models\CitizenActivationRequest;
use App\Models\User;
use App\Services\Oversight\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InPersonActivation
{
    public function __construct(private RegistrationSms $sms) {}

    public static function local(): bool
    {
        return LocalSms::enabled() && config('registration.activation_fingerprints') === true;
    }

    public function binding(User $citizen): string
    {
        return hash_hmac('sha256', json_encode([$citizen->id, $citizen->national_id, $citizen->birth_certificate_number,
            $citizen->identity_path, $citizen->registration_status, $citizen->phone, $citizen->is_active,
            $citizen->account_activated_at?->toISOString(), $citizen->identity_verified_at?->toISOString()], JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** Every stage locks users in the same order as final activation. */
    private function locked(User $actor, User $citizen, callable $work): mixed
    {
        return DB::transaction(function () use ($actor, $citizen, $work) {
            $users = User::whereIn('id', [$actor->id, $citizen->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $citizen = $users->get($citizen->id);
            abort_unless($actor && $citizen && ! $actor->is($citizen), 403);
            Gate::forUser($actor)->authorize('activateCitizen', $citizen);
            if ($citizen->hasActivatedIdentity()) {
                $this->fail('activation', 'الحساب معتمد بالفعل.');
            }
            $visit = CitizenActivationRequest::where('user_id', $citizen->id)->lockForUpdate()->first();

            return $work($actor, $citizen, $visit);
        });
    }

    public function begin(User $actor, User $citizen, array $data): CitizenActivationRequest
    {
        return $this->locked($actor, $citizen, function ($actor, $citizen, $visit) use ($data) {
            if ($visit && ! in_array($visit->status, ['pending', 'review_required', 'approved'], true)) {
                $this->fail('activation', 'حالة طلب التفعيل لا تسمح بالمراجعة.');
            }
            $national = $citizen->national_id; // Never infer a national ID from username, account ID or phone.
            if ($national && ($data['identity_type'] !== 'national_id' || $data['identity_number'] !== $national)) {
                $this->fail('identity_number', 'يجب استخدام الرقم الوطني المسجل دون تغييره.');
            }
            if (! $national && ($data['identity_type'] !== 'birth_certificate' || ($citizen->birth_certificate_number && $citizen->birth_certificate_number !== $data['identity_number']))) {
                $this->fail('identity_number', 'استخدم رقم شهادة الميلاد المسجل وفق مسار الحساب الأولي.');
            }
            if ($data['phone'] !== $citizen->phone && empty($data['phone_change_confirmed'])) {
                $this->fail('phone_change_confirmed', 'أكد تعديل رقم هاتف الحساب؛ سيُحفظ التغيير بعد نجاح الاعتماد ويُسجل في التدقيق.');
            }
            $key = 'activation-begin:'.$citizen->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $this->fail('activation', 'بلغت حد بدء المراجعة. حاول بعد ساعة.');
            }
            RateLimiter::hit($key, 3600);
            $visit ??= (new CitizenActivationRequest)->forceFill(['user_id' => $citizen->id, 'submitted_at' => now(), 'status' => 'pending']);
            $visit->forceFill(['visit_token' => (string) Str::uuid(), 'visit_actor_id' => $actor->id,
                'identity_type' => $data['identity_type'], 'identity_number' => $data['identity_number'],
                'identity_source' => $national ? 'account_national_id' : 'staff_birth_certificate', 'visit_phone' => $data['phone'],
                'account_binding' => $this->binding($citizen), 'visit_expires_at' => now()->addHour(),
                'otp_hash' => null, 'otp_source' => null, 'otp_attempts' => 0, 'otp_sends' => 0,
                'otp_sent_at' => null, 'otp_expires_at' => null, 'otp_verified_at' => null, 'visit_consumed_at' => null])->save();
            app(AuditTrail::class)->record('citizen_account.visit_started', 'activation_request', $visit->id, $actor,
                changes: ['changed_fields' => $citizen->phone === $data['phone'] ? ['identity_review'] : ['identity_review', 'pending_phone_change']]);

            return $visit;
        });
    }

    private function validVisit(?CitizenActivationRequest $visit, User $actor, User $citizen, string $token): CitizenActivationRequest
    {
        if (! $visit || ! $visit->visit_token || ! hash_equals($visit->visit_token, $token) || $visit->visit_actor_id !== $actor->id
            || ! $visit->visit_expires_at || now()->gte($visit->visit_expires_at) || $visit->visit_consumed_at
            || ! in_array($visit->status, ['pending', 'review_required', 'approved'], true)
            || ! hash_equals($visit->account_binding ?? '', $this->binding($citizen))) {
            $this->fail('activation', 'جلسة الحضور منتهية أو تغيرت بيانات الحساب. ابدأ مراجعة جديدة.');
        }

        return $visit;
    }

    public function passed(CitizenActivationRequest $visit, string $finger): bool
    {
        return ActivationFingerprintAttempt::where('activation_request_id', $visit->id)->where('visit_token', $visit->visit_token)
            ->where('actor_id', $visit->visit_actor_id)->where('finger', $finger)->where('source', 'local_simulation')->where('result', 'success')->exists();
    }

    public function finger(User $actor, User $citizen, array $data): ActivationFingerprintAttempt
    {
        abort_unless(self::local(), 403, 'محاكاة الأصابع متاحة محليًا فقط؛ لا يوجد جهاز حقيقي موصول.');

        return $this->locked($actor, $citizen, function ($actor, $citizen, $visit) use ($data) {
            $visit = $this->validVisit($visit, $actor, $citizen, $data['visit_token']);
            $existing = ActivationFingerprintAttempt::where('operation_token', $data['operation_token'])->first();
            $result = ! empty($data['force_failure']) ? 'failed' : 'success';
            if ($existing) {
                abort_unless($existing->activation_request_id === $visit->id && $existing->visit_token === $visit->visit_token
                    && $existing->actor_id === $actor->id && $existing->finger === $data['finger'] && $existing->result === $result, 409);

                return $existing;
            }
            if ($visit->otp_sends || $this->passed($visit, $data['finger'])) {
                $this->fail('finger', 'سجل نجاح هذا الإصبع بالفعل. لا يمكن تغييره بعد إرسال الرمز.');
            }
            if ($data['finger'] === 'left_index' && ! $this->passed($visit, 'right_index')) {
                $this->fail('finger', 'أكمل محاكاة السبابة اليمنى أولًا.');
            }
            $attempt = (new ActivationFingerprintAttempt)->forceFill(['activation_request_id' => $visit->id,
                'visit_token' => $visit->visit_token, 'operation_token' => $data['operation_token'], 'finger' => $data['finger'],
                'result' => $result, 'source' => 'local_simulation', 'actor_id' => $actor->id, 'performed_at' => now(),
                'failure_reason' => $result === 'failed' ? 'فشل تجريبي طلبه الموظف' : null]);
            $attempt->save();
            app(AuditTrail::class)->record('citizen_account.finger_'.$data['finger'], 'activation_request', $visit->id, $actor, $result);

            return $attempt;
        });
    }

    public function send(User $actor, User $citizen, string $token): array
    {
        $result = $this->locked($actor, $citizen, function ($actor, $citizen, $visit) use ($token) {
            $visit = $this->validVisit($visit, $actor, $citizen, $token);
            if (! $this->passed($visit, 'right_index') || ! $this->passed($visit, 'left_index')) {
                $this->fail('finger', 'يجب نجاح محاكاة السبابتين قبل إرسال الرمز.');
            }
            $local = $this->sms instanceof LocalSms;
            // Trial finger evidence cannot authorize activation outside the local environment.
            if (! self::local() || ($local && ! LocalSms::enabled()) || $this->sms instanceof UnavailableSms) {
                $this->fail('otp', 'التفعيل متوقف: يلزم ربط SMS موثوق وجهاز بصمة معتمد خارج البيئة المحلية. لم تُرسل رسالة.');
            }
            $key = 'activation-sms:'.hash_hmac('sha256', $visit->visit_phone, config('app.key'));
            if ($visit->otp_sends >= config('registration.max_sends') || $visit->otp_attempts >= config('registration.max_attempts')
                || ($visit->otp_sent_at && now()->lt($visit->otp_sent_at->copy()->addSeconds(config('registration.resend_seconds'))))
                || RateLimiter::tooManyAttempts($key, config('registration.max_sends'))) {
                $this->fail('otp', 'بلغت حد الإرسال أو المحاولات، أو لم تنته مهلة إعادة الإرسال.');
            }
            RateLimiter::hit($key, 3600);
            do {
                $code = (string) random_int(100000, 999999);
            } while ($visit->otp_hash && Hash::check($code, $visit->otp_hash));
            $visit->forceFill(['otp_sends' => $visit->otp_sends + 1, 'otp_sent_at' => now(), 'otp_hash' => null,
                'otp_verified_at' => null, 'otp_expires_at' => null, 'otp_source' => null])->save();
            try {
                if ($local) {
                    $this->sms->simulate();
                } else {
                    $this->sms->send($visit->visit_phone, $code);
                }
            } catch (\Throwable) {
                app(AuditTrail::class)->record('citizen_account.otp_delivery', 'activation_request', $visit->id, $actor, 'failed');

                return ['failed' => true];
            }
            $visit->forceFill(['otp_hash' => Hash::make($code), 'otp_expires_at' => now()->addMinutes(config('registration.otp_minutes')),
                'otp_source' => $local ? 'local_trial' : 'sms_provider'])->save();
            app(AuditTrail::class)->record('citizen_account.otp_delivery', 'activation_request', $visit->id, $actor);

            return ['source' => $visit->otp_source, 'message' => $local ? 'رمز تجريبي محلي لم يُرسل إلى شبكة الهاتف.' : 'رسالة SMS أُرسلت عبر المزود.',
                'local_code' => $local ? $code : null];
        });
        if (isset($result['failed'])) {
            $this->fail('otp', 'تعذر إرسال الرمز عبر المزود. لم يفعّل الحساب.');
        }

        return $result;
    }

    public function verify(User $actor, User $citizen, string $token, string $code): void
    {
        $valid = $this->locked($actor, $citizen, function ($actor, $citizen, $visit) use ($token, $code) {
            $visit = $this->validVisit($visit, $actor, $citizen, $token);
            if (! self::local() || ! $this->passed($visit, 'right_index') || ! $this->passed($visit, 'left_index')
                || ! $visit->otp_hash || ! $visit->otp_expires_at || now()->gte($visit->otp_expires_at)
                || $visit->otp_attempts >= config('registration.max_attempts')) {
                $this->fail('otp', 'الرمز منتهي أو تجاوز حد المحاولات، أو لم تكتمل خطوات الحضور.');
            }
            $ok = Hash::check($code, $visit->otp_hash);
            $visit->forceFill(['otp_attempts' => $visit->otp_attempts + 1, 'otp_verified_at' => $ok ? now() : null])->save();
            app(AuditTrail::class)->record('citizen_account.otp_checked', 'activation_request', $visit->id, $actor, $ok ? 'success' : 'failed');

            return $ok;
        });
        if (! $valid) {
            $this->fail('otp', 'رمز التحقق غير صحيح.');
        }
    }

    /** Called only inside the existing final transaction, after its fresh authorization and user locks. */
    public function consume(User $actor, User $citizen, array $data, bool $applyChanges = true): CitizenActivationRequest
    {
        $visit = CitizenActivationRequest::where('user_id', $citizen->id)->lockForUpdate()->first();
        $visit = $this->validVisit($visit, $actor, $citizen, $data['visit_token'] ?? '');
        if (! self::local() || ! $visit->otp_verified_at || ! $visit->otp_expires_at || now()->gte($visit->otp_expires_at)
            || ! $this->passed($visit, 'right_index') || ! $this->passed($visit, 'left_index')) {
            $this->fail('otp', 'أكمل محاكاة الإصبعين والتحقق من الرمز قبل الاعتماد.');
        }
        if ($visit->identity_type === 'national_id' && $visit->identity_number !== $data['national_id']) {
            $this->fail('national_id', 'الهوية لا تطابق جلسة الحضور.');
        }
        if ($visit->identity_type === 'birth_certificate' && $data['action'] !== 'confirm_issuance') {
            $this->fail('action', 'رقم شهادة الميلاد لا يحل محل الإصدار الرسمي للحساب الأولي.');
        }
        $changed = $citizen->phone !== $visit->visit_phone;
        $updates = ['phone' => $visit->visit_phone, 'phone_verified_at' => now()];
        if ($visit->identity_type === 'birth_certificate') {
            $updates['birth_certificate_number'] = $visit->identity_number;
        }
        if ($applyChanges) {
            $citizen->forceFill($updates)->save();
        }
        $visit->forceFill(['visit_consumed_at' => now(), 'otp_hash' => null])->save();
        app(AuditTrail::class)->record('citizen_account.visit_consumed', 'activation_request', $visit->id, $actor,
            changes: ['changed_fields' => $applyChanges ? ($changed ? ['phone', 'phone_verified_at'] : ['phone_verified_at']) : []]);

        return $visit;
    }
}
