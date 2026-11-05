<?php

namespace App\Services\Registration;

use App\Models\CitizenActivationRequest;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Oversight\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MockIdentityVerification
{
    public function queue(User $user, string $result, string $reason, array $evidence = [], bool $pending = true): CitizenActivationRequest
    {
        $row = $user->activationRequest()->lockForUpdate()->first() ?? (new CitizenActivationRequest)->forceFill(['user_id' => $user->id, 'revision' => 0]);
        $history = $row->evidence_history ?? [];
        if ($row->evidence) {
            $history[] = ['revision' => $row->revision, 'evidence' => $row->evidence];
        }
        $row->forceFill(['workflow' => 'linked_mock', 'status' => 'pending', 'submitted_at' => now(), 'review_reason' => $reason, 'revision' => $row->revision + 1, 'document_kind' => $result, 'evidence' => $evidence, 'evidence_history' => $history, 'reviewed_at' => null, 'reviewed_by' => null])->save();
        if (! $pending) {
            $row->forceFill(['status' => 'record_linked'])->save();
            $user->notifyNow(new CitizenActivity('linked-record:'.$row->id.':'.$row->revision, ['kind' => 'account_status', 'title' => 'تم ربط القيد التجريبي', 'body' => 'تم العثور على قيد المواليد والأسرة. يبقى الحساب أوليًا لاستكمال إصدار البطاقة، ولم يُنشأ رقم وطني.']));

            return $row;
        }
        $user->notifyNow(new CitizenActivity('linked-queue:'.$row->id.':'.$row->revision, ['kind' => 'account_status', 'title' => 'طلب تفعيل قيد المراجعة', 'body' => 'عزيزي المواطن، تم استلام طلب تفعيل حسابك الإلكتروني، وهو حالياً قيد المراجعة لدى الموظف المختص. سيتم إشعارك عند الانتهاء.']));
        // Notify only staff who can actually review activation requests, not all employees.
        User::where('role', 'employee')->where('is_active', true)->each(function ($staff) use ($user, $row) {
            if (Gate::forUser($staff)->allows('activateCitizen', $user)) {
                $staff->notifyNow(new CitizenActivity('linked-staff:'.$row->id.':'.$row->revision, ['kind' => 'activation_request', 'citizen_id' => $user->id, 'title' => 'طلب تفعيل جديد للمراجعة', 'body' => 'يوجد طلب تفعيل تجريبي جديد في قائمة تفعيل الحسابات.']));
            }
        });

        return $row;
    }

    public function capture(User $user, UploadedFile $image, string $token): string
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        $path = $image->store('mock-face/'.$user->id, 'attachments');
        if (! $path) {
            throw ValidationException::withMessages(['selfie' => 'تعذر حفظ الصورة الخاصة. أعد المحاولة.']);
        }
        try {
            return DB::transaction(function () use ($user, $path, $token) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->is_active && $user->role === 'citizen' && $user->activation_source === 'linked_local_mock' && $user->identity_path === 'has_identity' && ! $user->hasActivatedIdentity(), 403);
                abort_if(in_array($user->registration_status, ['under_review', 'suspended'], true), 409);
                abort_if(DB::table('mock_face_attempts')->where('operation_token', $token)->exists(), 409);
                $review = $user->activationRequest;
                $baseline = in_array($review?->status, ['retake_photo', 'requires_correction'], true) ? (int) data_get($review->evidence, 'review_attempt_baseline', 0) : 0;
                $count = DB::table('mock_face_attempts')->where('user_id', $user->id)->where('id', '>', $baseline)->count();
                abort_if($count >= config('citizen_mock.max_face_attempts'), 409, 'استنفدت المحاولات؛ انتظر مراجعة الموظف.');
                $person = DB::table('mock_civil_persons')->find($user->mock_person_id);
                abort_unless($person && $person->national_id === $user->national_id, 409);
                $live = (bool) $person->liveness_pass;
                $result = ! $live ? 'LIVENESS_FAILED' : (! $person->reference_photo || ! Storage::disk('attachments')->exists($person->reference_photo) ? 'NO_REFERENCE_IMAGE' : $person->face_scenario);
                if (! in_array($result, ['MATCH', 'REVIEW', 'NO_MATCH', 'NO_REFERENCE_IMAGE', 'IMAGE_QUALITY_FAILED', 'LIVENESS_FAILED'], true)) {
                    $result = 'REVIEW';
                }
                DB::table('mock_face_attempts')->insert(['user_id' => $user->id, 'operation_token' => $token, 'image_path' => $path, 'result' => $result, 'confidence' => $result === 'MATCH' ? 98.50 : ($result === 'REVIEW' ? 60 : null), 'liveness_pass' => $live, 'source' => 'deterministic_local_mock', 'created_at' => now(), 'updated_at' => now()]);
                if ($result === 'MATCH') {
                    $user->forceFill(['registration_status' => 'active', 'account_activated_at' => now(), 'identity_verified_at' => now(), 'activation_source' => 'automated_local_mock'])->save();
                    $user->notifyNow(new CitizenActivity('linked-activated:'.$user->id, ['kind' => 'account_status', 'title' => 'تم تفعيل الحساب تجريبيًا', 'body' => 'عزيزي المواطن، تم تفعيل حسابك الإلكتروني بنجاح. يمكنك الآن استخدام خدمات المنصة. مصدر النتيجة محاكاة محلية وليست تحققًا حكوميًا أو بيومتريًا.']));
                } elseif (in_array($result, ['REVIEW', 'NO_REFERENCE_IMAGE'], true) || $count + 1 >= config('citizen_mock.max_face_attempts')) {
                    $this->queue($user, $result, 'نتيجة تحقق وجه تجريبية: '.$result, ['selfie' => ['path' => $path, 'mime' => 'image/jpeg']]);
                    $user->forceFill(['registration_status' => 'under_review'])->save();
                } else {
                    $user->forceFill(['registration_status' => 'action_required'])->save();
                    $user->notifyNow(new CitizenActivity('linked-retry:'.$user->id.':'.($count + 1), ['kind' => 'account_status', 'title' => 'يلزم إعادة التقاط الصورة', 'body' => 'تعذر إتمام التحقق من هويتك إلكترونياً. يرجى الدخول إلى حسابك لمعرفة الإجراء المطلوب.']));
                }
                app(AuditTrail::class)->record('citizen_account.mock_face_result', 'user', $user->id, $user, $result, ['status' => $user->registration_status]);

                return $result;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('attachments')->delete($path);
            }
            throw $e;
        }
    }

    public function review(User $actor, User $user, array $data): void
    {
        abort_unless(MockCivilSystems::enabled(), 404);
        DB::transaction(function () use ($actor, $user, $data) {
            $locked = User::whereIn('id', [$actor->id, $user->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $locked[$actor->id];
            $user = $locked[$user->id];
            $actor->reviewPermissions()->lockForUpdate()->get();
            Gate::forUser($actor)->authorize('activateCitizen', $user);
            abort_if($actor->is($user), 403);
            $row = $user->activationRequest()->lockForUpdate()->firstOrFail();
            abort_unless($row->workflow === 'linked_mock' && $row->status === 'pending' && $row->revision === (int) $data['revision'], 409);
            $decision = $data['decision'];
            if ($decision === 'approved') {
                $person = DB::table('mock_civil_persons')->find($user->mock_person_id);
                abort_unless($person, 422, 'لا يوجد قيد مرتبط؛ يلزم إحالة إلى الجهة المختصة.');
                if ($user->identity_path === 'has_identity') {
                    abort_unless($person->national_id === $user->national_id && DB::table('mock_identity_cards')->where('person_id', $person->id)->where('status', 'active')->exists() && DB::table('mock_face_attempts')->where('user_id', $user->id)->exists(), 422);
                    $user->forceFill(['registration_status' => 'active', 'account_activated_at' => now(), 'identity_verified_at' => now(), 'activation_source' => 'employee_local_mock']);
                } else {
                    // Civil record review never fabricates a national ID or bypasses official first issuance.
                    $user->forceFill(['registration_status' => 'initial_account']);
                }
            } else {
                $user->forceFill(['registration_status' => in_array($decision, ['retake_photo', 'requires_correction'], true) ? 'action_required' : 'under_review']);
            }
            $user->save();
            if (in_array($decision, ['retake_photo', 'requires_correction'], true)) {
                $row->forceFill(['evidence' => array_replace($row->evidence ?? [], ['review_attempt_baseline' => (int) DB::table('mock_face_attempts')->where('user_id', $user->id)->max('id')])]);
            }
            $row->forceFill(['status' => $decision, 'review_reason' => $data['reason'], 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            app(AuditTrail::class)->recordPrivate('citizen_account.mock_review', 'activation_request', $row->id, $actor, changes: ['action' => $decision, 'status' => $user->registration_status], privateReason: $data['reason']);
            $user->notifyNow(new CitizenActivity('linked-decision:'.$row->id.':'.$row->revision, ['kind' => 'account_status', 'title' => $decision === 'approved' ? 'اعتماد المراجعة التجريبية' : 'تحديث طلب التفعيل', 'body' => $data['reason']]));
        });
    }
}
