<?php

namespace App\Services\Registration;

use App\Contracts\RegistrationSms;
use App\Models\CitizenActivationRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Oversight\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RemoteActivation
{
    public function __construct(private RegistrationSms $sms) {}

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function citizen(User $citizen): void
    {
        abort_if($citizen->activation_source !== null, 409, 'استخدم مسار تفعيل الحساب الحالي.');
        abort_unless($citizen->is_active && $citizen->role === 'citizen', 403);
        if ($citizen->hasActivatedIdentity()) {
            $this->fail('activation', 'الحساب مفعّل بالفعل.');
        }
        if ($citizen->registration_status === 'initial_account' || $citizen->identity_path === 'no_identity') {
            $this->fail('activation', 'الحساب الأولي دون رقم وطني يستكمل مسار إصدار البطاقة أولًا؛ لا ينشئ التفعيل الإلكتروني رقمًا وطنيًا.');
        }
    }

    public function send(User $citizen, array $data): ?string
    {
        return DB::transaction(function () use ($citizen, $data) {
            $citizen = User::whereKey($citizen->id)->lockForUpdate()->firstOrFail();
            $this->citizen($citizen);
            if (! filled($citizen->nationalIdentity()) || ! hash_equals($citizen->nationalIdentity(), $data['national_id'])) {
                $this->fail('national_id', 'الرقم الوطني لا يطابق سجل حسابك.');
            }
            $registry = app(InPersonActivation::class)->registryCheck($citizen, 'national_id', $data['national_id']);
            $row = $citizen->activationRequest()->lockForUpdate()->first();
            if ($row?->workflow === 'remote' && $row->status === 'pending') {
                $this->fail('activation', 'طلبك بانتظار الموظف؛ لا تُعد إرساله.');
            }
            $local = $this->sms instanceof LocalSms;
            if ($this->sms instanceof UnavailableSms || ($local && ! LocalSms::enabled())) {
                $this->fail('phone', 'خدمة SMS غير موصولة. لم تُرسل رسالة ولم يتم التحقق من الهاتف.');
            }
            $key = 'remote-activation-send:'.$citizen->id;
            $phoneKey = 'remote-activation-phone:'.hash_hmac('sha256', $data['phone'], config('app.key'));
            if (RateLimiter::tooManyAttempts($key, 3) || RateLimiter::tooManyAttempts($phoneKey, 5)) {
                $this->fail('phone', 'تجاوزت حد الإرسال. حاول لاحقًا.');
            }
            if ($row?->otp_sent_at?->gt(now()->subSeconds(60))) {
                $this->fail('phone', 'انتظر دقيقة قبل إعادة الإرسال.');
            }
            RateLimiter::hit($key, 3600);
            RateLimiter::hit($phoneKey, 3600);
            $code = (string) random_int(100000, 999999);
            try {
                if ($local) {
                    $this->sms->simulate();
                } else {
                    $this->sms->send($data['phone'], $code);
                }
            } catch (\Throwable) {
                $this->fail('phone', 'تعذر إرسال الرمز؛ لم يتم التحقق من رقم الهاتف.');
            }
            $row ??= (new CitizenActivationRequest)->forceFill(['user_id' => $citizen->id, 'submitted_at' => now()]);
            $row->forceFill(['workflow' => 'remote', 'status' => 'collecting', 'visit_token' => (string) Str::uuid(), 'visit_actor_id' => null,
                'identity_type' => 'national_id', 'identity_number' => $data['national_id'], 'identity_source' => $registry['source'],
                'visit_phone' => $data['phone'], 'account_binding' => app(InPersonActivation::class)->binding($citizen),
                'otp_hash' => Hash::make($code), 'otp_sent_at' => now(), 'otp_expires_at' => now()->addMinutes(3), 'otp_verified_at' => null,
                'otp_attempts' => 0, 'otp_sends' => $row->otp_sends + 1, 'otp_source' => $local ? 'local_trial' : 'sms_provider',
                'visit_consumed_at' => null, 'visit_expires_at' => now()->addHour()])->save();
            app(AuditTrail::class)->record('citizen_account.remote_code_requested', 'activation_request', $row->id, $citizen, changes: ['action' => $row->otp_source]);

            return $local ? $code : null;
        });
    }

    public function verify(User $citizen, string $code): void
    {
        $error = DB::transaction(function () use ($citizen, $code) {
            $citizen = User::whereKey($citizen->id)->lockForUpdate()->firstOrFail();
            $this->citizen($citizen);
            $row = $citizen->activationRequest()->lockForUpdate()->firstOrFail();
            if ($row->workflow !== 'remote' || $row->status !== 'collecting' || ! $row->otp_hash || ! $row->otp_expires_at?->isFuture() || $row->otp_attempts >= 5) {
                return 'انتهت صلاحية الرمز أو تجاوزت المحاولات. اطلب رمزًا جديدًا.';
            }
            if (! hash_equals($row->account_binding, app(InPersonActivation::class)->binding($citizen))) {
                return 'تغيرت بيانات حسابك. أعد التحقق من الهوية والهاتف.';
            }
            if ($row->otp_source === 'local_trial' && ! LocalSms::enabled()) {
                return 'المحاكاة المحلية غير متاحة.';
            }
            $row->otp_attempts++;
            if (! Hash::check($code, $row->otp_hash)) {
                $row->save();

                return 'رمز التحقق غير صحيح.';
            }
            $row->forceFill(['otp_verified_at' => now(), 'otp_hash' => null])->save();
            app(AuditTrail::class)->record('citizen_account.remote_phone_verified', 'activation_request', $row->id, $citizen);

            return null;
        });
        if ($error) {
            $this->fail('otp', $error);
        }
    }

    public function submit(User $citizen, array $data): void
    {
        $stored = [];
        try {
            DB::transaction(function () use ($citizen, $data, &$stored) {
                $citizen = User::whereKey($citizen->id)->lockForUpdate()->firstOrFail();
                $this->citizen($citizen);
                $row = $citizen->activationRequest()->lockForUpdate()->firstOrFail();
                $this->proof($citizen, $row);
                abort_unless(in_array($row->status, ['collecting', 'requires_correction', 'rejected'], true), 409);
                $evidence = [];
                foreach ($data['document_kind'] === 'identity_card' ? ['front', 'back', 'selfie'] : ['front', 'selfie'] as $field) {
                    $file = $data[$field];
                    $path = $file->store('activation-evidence/'.$citizen->id, 'attachments');
                    if (! $path) {
                        throw new \RuntimeException('Private upload failed');
                    }
                    $stored[] = $path;
                    $evidence[$field] = ['path' => $path, 'mime' => $file->getMimeType()];
                }
                $history = $row->evidence_history ?? [];
                if ($row->evidence) {
                    $history[] = ['revision' => $row->revision, 'evidence' => $row->evidence, 'document_kind' => $row->document_kind, 'review_reason' => $row->review_reason, 'reviewed_by' => $row->reviewed_by, 'reviewed_at' => $row->reviewed_at?->toIso8601String()];
                }
                $row->forceFill(['evidence_history' => $history, 'evidence' => $evidence, 'document_kind' => $data['document_kind'], 'revision' => $row->revision + 1,
                    'status' => 'pending', 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_reason' => null])->save();
                $serviceId = GovernmentService::where('code', 'identity-first-issue')->value('id');
                User::where('role', 'employee')->where('is_active', true)->whereHas('reviewPermissions', fn ($q) => $q->where('government_service_id', $serviceId)->where('can_activate', true))->chunkById(100, function ($staff) use ($row) {
                    foreach ($staff as $employee) {
                        $employee->notifyNow(new CitizenActivity('remote-activation:'.$row->id.':'.$row->revision, ['kind' => 'activation_request', 'title' => 'طلب تفعيل إلكتروني للمراجعة', 'body' => 'وصل طلب ببيانات الهاتف والوثائق والسيلفي. راجع المستندات قبل الاعتماد.', 'citizen_id' => $row->user_id]));
                    }
                });
                app(AuditTrail::class)->record('citizen_account.remote_submitted', 'activation_request', $row->id, $citizen, changes: ['status' => 'pending']);
            });
        } catch (\Throwable $error) {
            foreach ($stored as $path) {
                Storage::disk('attachments')->delete($path);
            } throw $error;
        }
    }

    private function proof(User $citizen, CitizenActivationRequest $row): void
    {
        if ($row->workflow !== 'remote' || ! $row->otp_verified_at || ! $row->visit_expires_at?->isFuture() || $row->visit_consumed_at
            || ! hash_equals($row->account_binding ?? '', app(InPersonActivation::class)->binding($citizen))
            || ($row->otp_source === 'local_trial' && ! LocalSms::enabled())) {
            $this->fail('activation', 'أعد التحقق من الرقم الوطني والهاتف أولًا؛ جلسة التقديم مدتها ساعة بعد طلب الرمز.');
        }
    }

    public function consume(User $actor, User $citizen, array $data, bool $applyChanges = true): CitizenActivationRequest
    {
        $row = $citizen->activationRequest()->lockForUpdate()->firstOrFail();
        // Review may occur after the citizen's one-hour upload session; the submitted evidence remains bound to the account.
        if ($row->workflow !== 'remote' || $row->status !== 'pending' || ! $row->otp_verified_at || $row->visit_consumed_at
            || ! hash_equals($row->visit_token ?? '', $data['visit_token'] ?? '') || $row->revision !== (int) ($data['revision'] ?? 0)
            || ! hash_equals($row->account_binding ?? '', app(InPersonActivation::class)->binding($citizen))) {
            $this->fail('activation', 'الطلب غير جاهز أو تغيرت بيانات الحساب أو نسخة المستندات.');
        }
        if ($row->otp_source === 'local_trial' && ! LocalSms::enabled()) {
            $this->fail('activation', 'لا يعتمد رمز تجريبي خارج البيئة المحلية.');
        }
        if (! in_array($row->document_kind, ['identity_card', 'birth_certificate'], true)) {
            $this->fail('activation', 'نوع الوثيقة غير صالح.');
        }
        foreach ($row->document_kind === 'identity_card' ? ['front', 'back', 'selfie'] : ['front', 'selfie'] as $field) {
            if (! Storage::disk('attachments')->exists(data_get($row->evidence, $field.'.path', ''))) {
                $this->fail('activation', 'المستندات غير مكتملة.');
            }
        }
        if (empty($data['documents_reviewed']) || empty($data['selfie_reviewed']) || ($row->document_kind === 'birth_certificate' && empty($data['birth_reviewed']))) {
            $this->fail('documents_reviewed', 'أكمل مراجعة البيانات والوثائق والسيلفي والمراجعة الإضافية لشهادة الميلاد.');
        }
        $row->forceFill(['visit_consumed_at' => now()])->save();
        if ($applyChanges && $citizen->phone !== $row->visit_phone) {
            $citizen->forceFill(['phone' => $row->visit_phone, 'phone_verified_at' => $row->otp_verified_at])->save();
            app(AuditTrail::class)->record('citizen_account.remote_phone_changed', 'user', $citizen->id, $actor, changes: ['changed_fields' => ['phone']]);
        }
        if ($applyChanges) {
            $citizen->forceFill(['phone_verified_at' => $row->otp_verified_at])->save();
        }

        return $row;
    }

    public function review(User $actor, User $citizen, array $data): void
    {
        DB::transaction(function () use ($actor, $citizen, $data) {
            $users = User::whereIn('id', [$actor->id, $citizen->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users[$actor->id];
            $citizen = $users[$citizen->id];
            Gate::forUser($actor)->authorize('activateCitizen', $citizen);
            abort_if($actor->is($citizen), 403);
            $row = $citizen->activationRequest()->lockForUpdate()->firstOrFail();
            abort_unless($row->workflow === 'remote' && $row->status === 'pending' && $row->revision === (int) $data['revision'], 409);
            $row->forceFill(['status' => $data['decision'], 'review_reason' => $data['reason'], 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            $citizen->notifyNow(new CitizenActivity('remote-activation-review:'.$row->id.':'.$row->revision, ['kind' => 'account_status', 'title' => $data['decision'] === 'rejected' ? 'رُفض طلب تفعيل الحساب' : 'طلب التفعيل يحتاج استكمالًا', 'body' => $data['reason']]));
            app(AuditTrail::class)->record('citizen_account.remote_reviewed', 'activation_request', $row->id, $actor, changes: ['status' => $data['decision']]);
        });
    }
}
