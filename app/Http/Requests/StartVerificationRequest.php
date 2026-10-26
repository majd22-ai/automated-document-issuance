<?php

namespace App\Http\Requests;

use App\Models\CitizenServiceRequest;
use App\Services\Integrations\IntegrationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = CitizenServiceRequest::findOrFail($this->route('id'));

        return $this->user()?->can('verifyExternal', $application) ?? false;
    }

    public function rules(): array
    {
        return ['kind' => ['required', Rule::in(array_keys(IntegrationRules::KINDS))], 'idempotency_key' => ['required', 'uuid'], 'target_document_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['missing'], 'verified' => ['missing'], 'result' => ['missing'], 'provider' => ['missing'], 'url' => ['missing'], 'subject' => ['missing']];
    }
}
