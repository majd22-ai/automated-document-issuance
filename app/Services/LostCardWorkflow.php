<?php

namespace App\Services;

use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\LostCardCase;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Oversight\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LostCardWorkflow
{
    public const SERVICE = 'identity-lost';

    public const EDITABLE_FIELDS = [
        'given_name' => 'اسم الفرد', 'father_name' => 'اسم الأب', 'grandfather_name' => 'اسم الجد', 'family_name' => 'اللقب',
        'issuing_governorate' => 'محافظة الإصدار', 'issuing_center' => 'مركز الإصدار', 'previous_issued_on' => 'تاريخ الإصدار السابق',
    ];

    public static function holderName(array $data): string
    {
        return Str::squish(implode(' ', array_map(fn ($part) => (string) ($data[$part] ?? ''), ['given_name', 'father_name', 'grandfather_name', 'family_name'])));
    }

    public static function enabled(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    public function service(): GovernmentService
    {
        return GovernmentService::where('code', self::SERVICE)->firstOrFail();
    }

    public function canOperate(User $user): bool
    {
        return self::enabled() && $user->is_active && ($user->role === 'admin'
            || $user->canReviewService(GovernmentService::where('code', self::SERVICE)->value('id'), 'can_review'));
    }

    public function cards(User $citizen): array
    {
        $cards = $citizen->documents()->where('document_type_code', 'identity')->where('status', 'active')
            ->get(['id', 'document_number'])->mapWithKeys(fn ($document) => ['document:'.$document->id => $document->document_number])->all();
        if ($citizen->mock_person_id && \App\Services\Registration\MockCivilSystems::enabled()) {
            $number = DB::table('mock_identity_cards')->where('person_id', $citizen->mock_person_id)
                ->where('status', 'active')->value('number');
            if ($number) {
                $cards['mock'] = $number;
            }
        }

        return $cards;
    }

    public function report(User $citizen, array $data): LostCardCase
    {
        return DB::transaction(function () use ($citizen, $data) {
            $citizen = User::whereKey($citizen->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($citizen)->authorize('create', CitizenServiceRequest::class);
            $national = $citizen->national_id;
            if (! $national && $citizen->mock_person_id && \App\Services\Registration\MockCivilSystems::enabled()) {
                $national = DB::table('mock_civil_persons')->where('id', $citizen->mock_person_id)->value('national_id');
            }
            $number = $this->cards($citizen)[$data['card']] ?? null;
            if (! $number || ! $national) {
                throw ValidationException::withMessages(['card' => 'اختر بطاقة شخصية مرتبطة بحسابك ورقمك الوطني.']);
            }
            $issuedOn = str_starts_with($data['card'], 'document:')
                ? $citizen->documents()->find(substr($data['card'], strlen('document:')))?->issued_at?->format('Y-m-d')
                : null;
            $key = hash('sha256', $citizen->id.':'.$number);
            $existing = LostCardCase::where('active_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $case = new LostCardCase;
            $case->forceFill([
                'reference' => 'LOSS-'.Str::ulid(), 'user_id' => $citizen->id, 'active_key' => $key,
                'submission_token' => (string) Str::uuid(), 'holder_name' => $citizen->name,
                'national_id' => $national, 'card_number' => $number, 'previous_issued_on' => $issuedOn,
                'lost_on' => $data['lost_on'], 'loss_place' => $data['loss_place'],
            ])->save();
            $this->audit('reported', $case, $citizen);
            $serviceId = $this->service()->id;
            User::where('role', 'employee')->where('is_active', true)
                ->whereHas('reviewPermissions', fn ($query) => $query->where('government_service_id', $serviceId)->where('can_review', true))
                ->each(fn (User $staff) => $staff->notifyNow(new CitizenActivity('lost-card:reported:'.$case->id, [
                    'kind' => 'lost_card_report', 'lost_card_case_id' => $case->id,
                    'title' => 'طلب إبلاغ عن فقدان بطاقة', 'body' => 'يوجد طلب فقدان بطاقة جديد للمراجعة.',
                ])));

            return $case;
        }, 3);
    }

    public function recordPolice(LostCardCase $case, User $actor): void
    {
        DB::transaction(function () use ($case, $actor) {
            $case = $this->lockedCase($case, $actor);
            if ($case->police()->exists()) {
                return;
            }
            $case->police()->forceCreate(['reference' => 'POL-'.Str::ulid(), 'recorded_by' => $actor->id, 'status' => 'valid']);
            $this->audit('police_recorded', $case, $actor);
        }, 3);
    }

    public function requestNotice(LostCardCase $case, User $actor): void
    {
        DB::transaction(function () use ($case, $actor) {
            $case = $this->lockedCase($case, $actor);
            $police = $case->police;
            abort_unless($police?->status === 'valid', 409);
            if ($case->notice()->exists()) {
                return;
            }
            (new \App\Models\MockNewspaperLossNotice)->forceFill([
                'mock_police_loss_report_id' => $police->id, 'reference' => 'NEWS-'.Str::ulid(),
                'recorded_by' => $actor->id, 'status' => 'pending',
            ])->save();
            $this->audit('notice_requested', $case, $actor);
        }, 3);
    }

    public function publish(LostCardCase $case, User $actor, array $data): void
    {
        DB::transaction(function () use ($case, $actor, $data) {
            $case = $this->lockedCase($case, $actor);
            $notice = $case->notice;
            abort_unless($case->police?->status === 'valid' && $notice, 409);
            if ($notice->status === 'published') {
                return;
            }
            abort_unless($notice->status === 'pending', 409);
            if ($data['published_on'] < $case->lost_on) {
                throw ValidationException::withMessages(['published_on' => 'لا يمكن أن يسبق تاريخ النشر تاريخ الفقد.']);
            }
            $notice->forceFill($data + ['status' => 'published', 'published_by' => $actor->id])->save();
            $this->audit('notice_published', $case, $actor);
            $case->citizen->notifyNow(new CitizenActivity('lost-card:ready:'.$case->id, [
                'kind' => 'lost_card_ready', 'lost_card_case_id' => $case->id,
                'title' => 'يمكنك الآن تقديم طلب بدل فاقد',
                'body' => 'تم التبليغ عن فقدان بطاقتك الشخصية، وتم استكمال إجراءات بلاغ الشرطة ونشر إعلان الفقدان في صحيفة الثورة. يرجى الانتقال إلى تقديم طلب استخراج بطاقة شخصية بدل فاقد',
            ]));
            $this->audit('citizen_notified', $case, $actor);
        }, 3);
    }

    public function submit(LostCardCase $case, User $actor, array $data, ServiceRequestSubmission $submission): CitizenServiceRequest
    {
        return DB::transaction(function () use ($case, $actor, $data, $submission) {
            $citizen = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $case = LostCardCase::where('user_id', $citizen->id)->lockForUpdate()->findOrFail($case->id);
            if ($case->application_id) {
                return $case->application;
            }
            abort_unless($case->ready(), 409);
            if (Str::squish($data['signature']) !== Str::squish($case->holder_name)) {
                throw ValidationException::withMessages(['signature' => 'اكتب اسم صاحب الطلب كما هو مسجل في بيانات الحساب.']);
            }
            if (self::holderName($data) !== Str::squish($case->holder_name)) {
                throw ValidationException::withMessages(['given_name' => 'رتّب أجزاء الاسم بحيث تطابق الاسم الكامل في بيانات الحساب.']);
            }
            if ($case->previous_issued_on && $data['previous_issued_on'] !== $case->previous_issued_on) {
                throw ValidationException::withMessages(['previous_issued_on' => 'تاريخ إصدار البطاقة يجب أن يطابق السجل.']);
            }
            $service = $this->service();
            $payload = ['data' => $data + [
                'national_id' => $case->national_id, 'lost_card_number' => $case->card_number,
                'loss_reference' => $case->reference, 'police_reference' => $case->police->reference,
                'notice_reference' => $case->notice->reference, 'notice_published_on' => $case->notice->published_on,
                'notice_issue' => $case->notice->issue_number, 'notice_page' => $case->notice->page_number,
            ]];
            $preview = $submission->preview($citizen, $service, $payload);
            $application = $submission->submit($citizen, $service, $case->submission_token, $preview);
            $case->forceFill(['application_id' => $application->id])->save();
            $this->audit('replacement_submitted', $case, $citizen);

            return $application;
        }, 3);
    }

    private function lockedCase(LostCardCase $case, User $actor): LostCardCase
    {
        abort_unless($this->canOperate($actor->fresh()), 403);

        return LostCardCase::lockForUpdate()->findOrFail($case->id);
    }

    private function audit(string $event, LostCardCase $case, User $actor): void
    {
        app(AuditTrail::class)->record('lost_card.'.$event, 'lost_card_case', $case->id, $actor);
    }
}
