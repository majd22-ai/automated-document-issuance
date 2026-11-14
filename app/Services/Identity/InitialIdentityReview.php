<?php

namespace App\Services\Identity;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\IdentityCardForm;
use App\Models\NeighborhoodOfficial;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Services\IdentityAttestations;
use App\Services\Operations\LockedRequest;
use App\Services\Registration\MockCivilSystems;
use App\Services\Requirements\RequirementResult;
use App\Services\Review\ReviewActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Initial review evidence is revision-bound; registry equality is never witness consent. */
class InitialIdentityReview
{
    public function tasks(IdentityCardForm $form): array
    {
        $form = $form->fresh(['photo', 'witnesses', 'attestation.official.user']);
        $photo = $form->photo;
        $validPhoto = $photo && $photo->user_id === $form->user_id
            && $photo->government_service_id === $form->government_service_id
            && $photo->citizen_service_request_id === $form->citizen_service_request_id
            && $photo->kind === 'photo' && in_array($photo->mime_type, ['image/png', 'image/jpeg'], true) && $photo->intact();
        if ($validPhoto) {
            $dimensions = @getimagesize(\Illuminate\Support\Facades\Storage::disk('attachments')->path($photo->storage_path));
            $validPhoto = $dimensions && $dimensions[0] >= 600 && $dimensions[1] >= 600
                && $dimensions['mime'] === $photo->mime_type && (! $photo->expires_at || ! $photo->expires_at->isPast());
        }
        $tasks['photo'] = $this->task('٤ — الصورة الشخصية', (bool) $validPhoto,
            'تُراجع جودة الصورة يدويًا. لا توجد صورة مرجعية موثوقة مرتبطة بهذا المسار؛ الصور التوضيحية في المحاكاة لا تثبت تطابق الوجه.',
            [$photo?->getAttributes(), $validPhoto], []);
        if (! $validPhoto) {
            $tasks['photo']['issues'][] = 'يلزم ملف صورة سليم مرتبط بهذا الطلب، بدقة ٦٠٠ × ٦٠٠ على الأقل ونوع PNG أو JPEG.';
        }
        foreach ([1, 2] as $position) {
            $witness = $form->witnesses->firstWhere('position', $position);
            $document = $witness && MockCivilSystems::enabled() ? DB::table('mock_civil_documents')
                ->where('document_type', $witness->document_type)->where('document_number', $witness->document_number)->first() : null;
            $person = $document ? DB::table('mock_civil_persons')->where('id', $document->person_id)->first() : null;
            $comparisons = [];
            foreach (['name' => 'الاسم', 'document_type' => 'نوع الوثيقة', 'document_number' => 'رقم الوثيقة', 'issued_place' => 'مكان الإصدار', 'issued_at' => 'تاريخ الإصدار'] as $key => $label) {
                $submitted = $key === 'issued_at' ? $witness?->issued_at?->format('Y-m-d') : $witness?->$key;
                $source = $key === 'name' ? $person?->name : ($document?->$key ?? null);
                $comparisons[] = ['label' => $label, 'submitted' => $submitted, 'source' => $source,
                    'match' => filled($submitted) && filled($source) && $this->normalize($submitted) === $this->normalize($source)];
            }
            $selfie = $witness?->selfieIntact() ?? false;
            $ready = $person && $person->complete && $person->id !== $form->citizen->mock_person_id
                && $document->status === 'active' && $document->issued_at <= now()->toDateString()
                && collect($comparisons)->every('match') && $selfie;
            $other = $form->witnesses->firstWhere('position', 3 - $position);
            if ($other && $witness && $other->document_type === $witness->document_type && $other->document_number === $witness->document_number) {
                $ready = false;
            }
            if ($other && $person && DB::table('mock_civil_documents')->where('document_type', $other->document_type)
                ->where('document_number', $other->document_number)->where('person_id', $person->id)->exists()) {
                $ready = false;
            }
            $tasks['witness_'.$position] = $this->task('٥ — الشاهد '.$position, (bool) $ready,
                'المصدر: سجل وثائق محاكاة التسجيل، بالرقم ونوع الوثيقة فقط. صورة الشاهد وتطابق السجل لا يثبتان موافقة الشاهد؛ سجّل دليلًا مستقلًا بعد التحقق منه.',
                [$witness?->only(['id', 'name', 'document_type', 'document_number', 'issued_place', 'issued_at', 'phone', 'selfie_path', 'selfie_sha256', 'selfie_size']), $document, $person, $selfie, $ready], $comparisons);
            $tasks['witness_'.$position]['source_id'] = $document?->id;
            $tasks['witness_'.$position]['source_phone'] = $person?->phone;
            $tasks['witness_'.$position]['source_checked_at'] = now()->format('Y-m-d H:i');
            if (! $document || ! $person) {
                $tasks['witness_'.$position]['issues'][] = 'لم توجد وثيقة بهذا النوع والرقم في مصدر المحاكاة المحدد.';
            } elseif (! $person->complete || $document->status !== 'active' || $document->issued_at > now()->toDateString()) {
                $tasks['witness_'.$position]['issues'][] = 'سجل الشاهد ناقص أو وثيقته غير سارية في مصدر المحاكاة.';
            }
            if (! $selfie) {
                $tasks['witness_'.$position]['issues'][] = 'يلزم استكمال صورة الشاهد مع وثيقته أو استبدال الملف التالف.';
            }
            if (! $ready && $person && collect($comparisons)->every('match') && $selfie) {
                $tasks['witness_'.$position]['issues'][] = 'تعذر اعتماد استقلال هوية الشاهد عن المواطن والشاهد الآخر أو صلاحية سجله.';
            }
        }
        $attestation = $form->attestation;
        $official = $attestation?->official;
        $validAttestation = IdentityAttestations::approvedForSubmission($attestation)
            && $attestation->form_revision === $form->revision && ! $attestation->cancelled_at
            && $attestation->decided_at?->lte(now()) && $attestation->verified_at?->lte(now())
            && $official?->user && NeighborhoodOfficial::authorized($official->user)
            && $official->is_active && $official->can_attest
            && $attestation->decided_by === $official->user_id
            && $this->normalize($attestation->citizen_name) === $this->normalize($form->data['applicant_name'] ?? $form->citizen->name)
            && (int) data_get($attestation->recipient_scope, 'employee_id') === $official->user_id;
        if ($validAttestation) {
            try {
                app(IdentityAttestations::class)->recipient(($attestation->recipient_scope ?? []) + ['official_id' => $official->id], $form);
            } catch (ValidationException) {
                $validAttestation = false;
            }
        }
        $tasks['attestation'] = $this->task('٦ — مصادقة العاقل', (bool) $validAttestation,
            'فحص آلي لسجل المصادقة الحالي وصلاحية العاقل ونطاق الحي وهوية صاحب القرار. صورة الختم وحدها لا تكفي.',
            [$attestation?->only(['id', 'form_revision', 'status', 'authority_type', 'reference', 'neighborhood_official_id', 'recipient_scope', 'citizen_name', 'decided_by', 'decided_at', 'verified_at', 'verification_source', 'cancelled_at']), $official?->getAttributes(), $official?->user?->only(['id', 'role', 'is_active']), $form->data, $validAttestation], []);
        $tasks['attestation']['reference'] = $attestation?->reference;
        $tasks['attestation']['verified_at'] = $attestation?->verified_at?->format('Y-m-d H:i');
        if (! $validAttestation) {
            $tasks['attestation']['issues'][] = 'المصادقة لا تستوفي النسخة الحالية، أو هوية صاحب القرار، أو صلاحية العاقل ونطاق الحي. راجع مرجع المصادقة قبل قبولها.';
        }
        foreach ($tasks as $key => &$task) {
            $task['fingerprint'] = hash('sha256', json_encode([$form->id, $form->revision, $key, $task['evidence']], JSON_THROW_ON_ERROR));
            unset($task['evidence']);
            $decision = ReviewDecision::where('citizen_service_request_id', $form->citizen_service_request_id)
                ->where('action', 'identity_step_review')->where('details->task', $key)->latest('id')->first();
            $task['approved'] = $task['ready'] && $decision && ($decision->details['outcome'] ?? '') === 'approved'
                && hash_equals($task['fingerprint'], $decision->details['fingerprint'] ?? '');
            $task['decision'] = $decision;
        }
        unset($task);
        return $tasks;
    }

    public function record(User $actor, CitizenServiceRequest $request, array $input): ReviewDecision
    {
        $input = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $input);
        $input = \Illuminate\Support\Facades\Validator::make($input, [
            'task' => ['required', 'in:photo,witness_1,witness_2,attestation'],
            'outcome' => ['required', 'in:approved,correction_required'], 'decision_token' => ['required', 'uuid'],
            'fingerprint' => ['required', 'string', 'size:64'], 'note' => ['nullable', 'string', 'max:1000'],
            'confirmed' => ['required_if:outcome,approved', 'accepted'],
            'consent_method' => ['nullable', 'in:signed_statement,independent_confirmation'],
            'consent_reference' => ['nullable', 'string', 'min:3', 'max:200'],
        ])->validate();
        return app(LockedRequest::class)->run($actor, $request, 'resolveReview', function ($actor, $owner, $service, $request) use ($input) {
            abort_unless($actor->role === 'employee', 403);
            abort_unless(IdentityJourney::applies($request), 404);
            $actions = app(ReviewActions::class);
            $context = ['decision_token' => $input['decision_token'], 'data' => $input];
            if ($decision = $actions->replay($request, $actor, 'identity_step_review', null, $context)) {
                return $decision;
            }
            $tasks = $this->tasks($request->identityCardForm);
            $task = $tasks[$input['task']] ?? null;
            if (! $task || ! hash_equals($task['fingerprint'], $input['fingerprint'])) {
                throw ValidationException::withMessages(['review' => 'تغيرت أدلة هذه المهمة؛ حدّث الصفحة وأعد المراجعة.']);
            }
            if ($input['outcome'] === 'approved') {
                if (! $task['ready']) {
                    throw ValidationException::withMessages(['review' => 'الأدلة ناقصة أو غير متطابقة؛ أعد العنصر للاستكمال.']);
                }
                if (str_starts_with($input['task'], 'witness_') && (empty($input['consent_reference']) || empty($input['consent_method']) || empty($input['confirmed']))) {
                    throw ValidationException::withMessages(['consent_reference' => 'يلزم تحقق مستقل من موافقة الشاهد وتسجيل طريقته ومرجعه.']);
                }
                if (empty($input['confirmed'])) {
                    throw ValidationException::withMessages(['confirmed' => 'أكد إتمام المراجعة اليدوية.']);
                }
                if ($input['task'] === 'photo') {
                    $request->identityCardForm->photo->forceFill(['review_status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'public_reason' => null])->save();
                    $task = $this->tasks($request->identityCardForm)['photo'];
                }
            } elseif (mb_strlen(trim($input['note'] ?? '')) < 3) {
                throw ValidationException::withMessages(['note' => 'حدد سبب الاستكمال لهذا العنصر.']);
            }
            $request->forceFill(['review_version' => $request->review_version + 1, 'requirements_result' => null, 'requirements_checked_at' => null])->save();
            return $actions->save($request, $actor, 'identity_step_review', $request->status, null, $context, null,
                ['task' => $input['task'], 'outcome' => $input['outcome'], 'fingerprint' => $task['fingerprint'],
                    'form_revision' => $request->identityCardForm->revision, 'source' => str_starts_with($input['task'], 'witness_') ? 'mock_civil_documents' : 'platform',
                    'source_id' => $task['source_id'], 'consent_method' => $input['consent_method'] ?? null,
                    'consent_reference' => $input['consent_reference'] ?? null, 'note' => $input['note'] ?? null]);
        });
    }

    public function requirements(CitizenServiceRequest $request, ?ServiceRequestStatus $target): array
    {
        if (! IdentityJourney::applies($request)) {
            return [];
        }
        $currentAcceptance = $request->reviewDecisions()->where('action', 'accept')->latest('id')->first();
        $inspection = $target === null && in_array($request->status, ['submitted', 'under_review', 'requires_correction'], true);
        if (! $inspection && $target !== ServiceRequestStatus::Accepted && (! in_array($target, [ServiceRequestStatus::Approved, ServiceRequestStatus::Processing, ServiceRequestStatus::Completed], true)
            || ($currentAcceptance?->details['initial_identity_review_version'] ?? null) !== 1)) {
            return [];
        }
        return collect($this->tasks($request->identityCardForm))->map(fn ($task, $key) => new RequirementResult(
            'initial_identity.'.$key, $task['label'], $task['approved'] ? RequirementState::Met : RequirementState::NeedsReview,
            $task['approved'] ? 'اكتملت مراجعة الأدلة الحالية وسُجل الموظف ووقت القرار.' : 'لم تكتمل مراجعة هذا العنصر على النسخة الحالية.', mandatory: ! $inspection
        ))->values()->all();
    }

    private function task(string $label, bool $ready, string $message, array $evidence, array $comparisons): array
    {
        return compact('label', 'ready', 'message', 'evidence', 'comparisons') + ['source_id' => null, 'source_phone' => null, 'issues' => []];
    }

    private function normalize(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value));
    }
}
