<?php

namespace App\Services\Integrations;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Services\Requirements\RequirementResult;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IntegrationRequirements
{
    public static function due(?CitizenServiceRequest $request, ?ServiceRequestStatus $target): bool
    {
        return $request && ($target === null ? in_array($request->status, ['approved', 'processing', 'completed'], true) : in_array($target, [ServiceRequestStatus::Approved, ServiceRequestStatus::Processing, ServiceRequestStatus::Completed], true));
    }

    public function checks(GovernmentService $service, ?CitizenServiceRequest $request, ?ServiceRequestStatus $target): array
    {
        try {
            $rules = $request ? app(IntegrationRules::class)->effective($request) : ($service->integration_rules ?? []);
            Validator::make(['integration_rules' => $rules], IntegrationRules::validation())->validate();
            $checks = [];
            foreach ($rules as $kind => $rule) {
                if (! $rule['required']) {
                    continue;
                }
                $due = self::due($request, $target);
                $state = $request ? app(IntegrationManager::class)->status($request, $kind) : ['status' => 'unknown', 'message' => 'لم يتم التحقق إلكترونيًا حتى الآن'];
                $checks[] = new RequirementResult('external.'.$kind, 'التحقق الخارجي: '.IntegrationRules::KINDS[$kind], $state['status'] === 'verified' ? RequirementState::Met : RequirementState::Unverifiable, $state['message'], 'staff', (bool) $due);
            }

            return $checks;
        } catch (ValidationException) {
            return [new RequirementResult('external.configuration', 'قواعد التكامل', RequirementState::Unverifiable, 'قواعد التحقق الخارجي غير صالحة أو متعارضة؛ راجع الإدارة.')];
        }
    }
}
