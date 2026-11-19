<?php

namespace App\Services\Identity;

use App\Http\Requests\SaveIdentityCardForm;
use App\Models\CitizenAttachment;
use App\Models\CitizenDocument;
use App\Models\GovernmentService;
use App\Models\IdentityCardForm;
use App\Models\User;
use App\Services\Requirements\PriorDocumentRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityRenewals
{
    public function create(User $actor): IdentityCardForm
    {
        return DB::transaction(function () use ($actor) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $service = GovernmentService::where('code', 'identity-renewal')->firstOrFail();
            abort_unless($actor->is_active && $actor->role === 'citizen' && $actor->allowsCitizenService($service), 403);
            abort_unless($service->is_active && $service->rules_approved, 404);
            $form = IdentityCardForm::where('user_id', $actor->id)->where('government_service_id', $service->id)->whereNull('submitted_at')->first();
            if (! $form) {
                $source = IdentityCardForm::where('user_id', $actor->id)->whereNotNull('submitted_at')->latest('id')->first();
                $form = new IdentityCardForm;
                $data = array_intersect_key($source?->data ?? [], config('identity_card.fields.3'));
                $form->forceFill(['user_id' => $actor->id, 'government_service_id' => $service->id, 'reference' => 'RENEW-'.Str::ulid(),
                    'status' => 'draft', 'journey_version' => 1, 'current_step' => 4, 'version' => 1,
                    'data' => array_replace($data, ['applicant_name' => $actor->name, 'phone' => $actor->phone])])->save();
            }
            return $form;
        });
    }

    public function previous(IdentityCardForm $form): ?CitizenDocument
    {
        return $form->citizen->documents()->where('document_type_code', 'identity')->find(data_get($form->data, 'previous_document_id'));
    }

    public function cardCopy(IdentityCardForm $form): ?CitizenAttachment
    {
        return CitizenAttachment::where('user_id', $form->user_id)->where('government_service_id', $form->government_service_id)
            ->where('citizen_service_request_id', $form->citizen_service_request_id)->where('document_code', 'renewal_previous_card')->first();
    }

    public function assertPrevious(IdentityCardForm $form): CitizenDocument
    {
        $previous = $this->previous($form);
        if (! $previous || $previous->successor()->exists()) {
            throw ValidationException::withMessages(['previous_document_id' => 'اختر بطاقة سابقة تخص حسابك ولم يسبق تجديدها.']);
        }
        [$check, $candidate] = app(PriorDocumentRequirements::class)->evaluate($form->citizen, $form->service, $previous);
        if (! $candidate) {
            throw ValidationException::withMessages(['previous_document_id' => $check->message]);
        }
        return $previous;
    }

    public function assertReady(IdentityCardForm $form): void
    {
        $this->assertPrevious($form);
        Validator::make(['data' => $form->data], SaveIdentityCardForm::dataRules(3), SaveIdentityCardForm::validationMessages())->validate();
        $copy = $this->cardCopy($form);
        if (! $copy || ! $copy->intact() || in_array($copy->review_status, ['rejected', 'replacement_required'], true)) {
            throw ValidationException::withMessages(['previous_card' => 'أرفق صورة واضحة وسليمة للبطاقة السابقة.']);
        }
        if (($form->data['applicant_name'] ?? '') !== $form->citizen->name) {
            throw ValidationException::withMessages(['identity' => 'تغيير بيانات الهوية الأساسية يحتاج إجراء تصحيح مستقل.']);
        }
    }

    public function fingerprint(IdentityCardForm $form): array
    {
        return [$form->data, $this->previous($form)?->getAttributes(), $this->cardCopy($form)?->only(['id', 'version_token', 'sha256', 'review_status'])];
    }
}
