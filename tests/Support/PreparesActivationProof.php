<?php

namespace Tests\Support;

use App\Models\ActivationFingerprintAttempt;
use App\Models\CitizenActivationRequest;
use App\Models\User;
use App\Services\Registration\InPersonActivation;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait PreparesActivationProof
{
    /** Trusted fixtures for legacy policy/issuance tests. New flow tests exercise the real stages. */
    protected function prepareActivationProof(User $citizen, User $actor): array
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['registration.local_otp' => true, 'registration.activation_fingerprints' => true]);
        $citizen->refresh();
        $visit = CitizenActivationRequest::where('user_id', $citizen->id)->first()
            ?? (new CitizenActivationRequest)->forceFill(['user_id' => $citizen->id, 'status' => 'pending', 'submitted_at' => now()]);
        $token = (string) Str::uuid();
        $visit->forceFill(['visit_token' => $token, 'visit_actor_id' => $actor->id,
            'identity_type' => $citizen->national_id ? 'national_id' : 'birth_certificate',
            'identity_number' => $citizen->national_id ?: ($citizen->birth_certificate_number ?: '123456789'),
            'identity_source' => $citizen->national_id ? 'account_national_id' : 'staff_birth_certificate',
            'visit_phone' => $citizen->phone ?: '+967777123456', 'account_binding' => app(InPersonActivation::class)->binding($citizen),
            'visit_expires_at' => now()->addHour(), 'otp_hash' => Hash::make('123456'), 'otp_source' => 'local_trial',
            'otp_attempts' => 0, 'otp_sends' => 1, 'otp_sent_at' => now(), 'otp_expires_at' => now()->addMinutes(10),
            'otp_verified_at' => now(), 'visit_consumed_at' => null])->save();
        foreach (['right_index', 'left_index'] as $finger) {
            (new ActivationFingerprintAttempt)->forceFill(['activation_request_id' => $visit->id, 'visit_token' => $token,
                'operation_token' => (string) Str::uuid(), 'finger' => $finger, 'result' => 'success',
                'source' => 'local_simulation', 'actor_id' => $actor->id, 'performed_at' => now()])->save();
        }

        return ['visit_token' => $token, 'otp' => '123456'];
    }
}
