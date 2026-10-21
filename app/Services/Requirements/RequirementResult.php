<?php

namespace App\Services\Requirements;

use App\Enums\RequirementState;

final readonly class RequirementResult
{
    public function __construct(
        public string $code, public string $label, public RequirementState $state,
        public string $message, public string $actor = 'staff', public bool $mandatory = true,
        public array $details = [],
    ) {}

    public function toArray(): array
    {
        return ['code' => $this->code, 'label' => $this->label, 'state' => $this->state->value, 'state_label' => $this->state->label(), 'message' => $this->message, 'actor' => $this->actor, 'mandatory' => $this->mandatory, 'details' => $this->details];
    }
}
