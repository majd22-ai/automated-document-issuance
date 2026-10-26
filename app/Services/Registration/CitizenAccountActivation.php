<?php

namespace App\Services\Registration;

use App\Models\CitizenAccountConfirmation;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Notifications\CitizenUpdates;
use App\Services\Oversight\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CitizenAccountActivation
{
    public function __construct(private CitizenIdentifiers $identifiers) {}

    public function confirm(User $actor, User $citizen, array $data): CitizenAccountConfirmation
    {
        $fingerprint = hash_hmac('sha256', json_encode([$data['action'], $data['national_id'], (int) ($data['request_id'] ?? 0), (bool) $data['identity_confirmed'], (bool) $data['official_issuance_confirmed'], $data['evidence_reference']], JSON_THROW_ON_ERROR), config('app.key'));
        $record = $this->identifiers->serialized(fn () => DB::transaction(function () use ($actor, $citizen, $data, $fingerprint) {
            $users = User::whereIn('id', [$actor->id, $citizen->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $citizen = $users->get($citizen->id);
            abort_unless($actor && $citizen, 403);
            $service = GovernmentService::where('code', 'identity-first-issue')->lockForUpdate()->firstOrFail();
            ServiceReviewPermission::where('user_id', $actor->id)->where('government_service_id', $service->id)->lockForUpdate()->first();
            Gate::forUser($actor)->authorize('activateCitizen', $citizen);
            $existing = CitizenAccountConfirmation::where('operation_token', $data['operation_token'])->first();
            if ($existing) {
                if ($existing->user_id !== $citizen->id || $existing->actor_id !== $actor->id || ! hash_equals($existing->payload_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['operation_token' => 'رمز العملية مستخدم لبيانات مختلفة. حدّث الصفحة وراجع العملية.']);
                }

                return $existing;
            }
            $request = null;
            $initialOrigin = $citizen->identity_path === 'no_identity' || $citizen->registration_status === 'initial_account';
            if ($data['action'] === 'confirm_issuance') {
                if (! $initialOrigin || ! $data['identity_confirmed'] || ! $data['official_issuance_confirmed']) {
                    throw ValidationException::withMessages(['action' => 'تأكيد الإصدار للحساب الأولي يتطلب إثباتًا رسميًا واعتماد الموظف.']);
                }
                $request = $citizen->serviceRequests()->whereKey($data['request_id'] ?? 0)->where('government_service_id', $service->id)->lockForUpdate()->first();
                $document = $request?->issuedDocument;
                if (! $request || $request->request_type !== 'issue' || $request->status !== 'completed' || ! $request->completed_at || $request->completed_at->isFuture() || ! $request->completed_by
                    || ! $document || $document->user_id !== $citizen->id || $document->government_service_id !== $service->id || $document->document_type_code !== 'identity'
                    || $document->status !== 'active' || ! $document->issued_by || ! $document->issued_recorded_at || $document->issued_recorded_at->isFuture() || $document->expires_at?->lt(today())) {
                    throw ValidationException::withMessages(['request_id' => 'أكمل مسار إصدار وتسليم طلب البطاقة المرتبط بهذا الحساب أولًا. السجل الداخلي وحده ليس تأكيدًا رسميًا.']);
                }
            } elseif ($data['action'] !== 'activate' || $initialOrigin || (string) $citizen->nationalIdentity() !== $data['national_id']) {
                throw ValidationException::withMessages(['national_id' => 'التفعيل لا يغيّر الهوية المسجلة. ترقية الحساب الأولي تتطلب تأكيد إصدار طلبه.']);
            }
            if ($citizen->account_activated_at && (! $data['identity_confirmed'] || $citizen->identity_verified_at) && $citizen->registration_status !== 'review_required') {
                throw ValidationException::withMessages(['action' => 'الاعتماد المطلوب مكتمل بالفعل. لا يسمح بتفعيل مكرر.']);
            }
            $conflict = $this->identifiers->conflicts($data['national_id'], $citizen->id);
            $record = (new CitizenAccountConfirmation)->forceFill([
                'user_id' => $citizen->id, 'actor_id' => $actor->id, 'citizen_service_request_id' => $request?->id,
                'operation_token' => $data['operation_token'], 'action' => $data['action'], 'result' => $conflict ? 'conflict' : 'confirmed',
                'evidence_reference' => $data['evidence_reference'], 'payload_fingerprint' => $fingerprint,
                'identity_confirmed' => (bool) $data['identity_confirmed'], 'confirmed_at' => now(),
            ]);
            $record->save();
            if ($conflict) {
                $citizen->forceFill(['registration_status' => 'review_required'])->save();
            } else {
                $citizen->forceFill(['national_id' => $data['national_id'], 'registration_status' => 'active', 'account_activated_at' => $citizen->account_activated_at ?? now()]);
                if ($data['identity_confirmed']) {
                    $citizen->identity_verified_at ??= now();
                }
                $citizen->save();
            }
            app(AuditTrail::class)->record($conflict ? 'citizen_account.identity_conflict' : 'citizen_account.confirmed', 'user', $citizen->id, $actor, $conflict ? 'conflict' : 'success', ['action' => $data['action'], 'status' => $citizen->registration_status]);
            app(CitizenUpdates::class)->accountConfirmation($citizen, $record);

            return $record;
        }));
        if ($record->result === 'conflict') {
            throw ValidationException::withMessages(['national_id' => 'تعارض في ربط الهوية. أوقفت العملية وأُحيل الحساب إلى المراجعة الآمنة دون دمج حسابات أو تغيير ملكيتها.']);
        }

        return $record;
    }
}
