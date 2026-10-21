<?php

namespace App\Services\Requirements;

use App\Enums\RequirementState;

final class EligibilityResult
{
    public array $checks = [];

    private string $checkedAt;

    public function __construct()
    {
        $this->checkedAt = now()->toIso8601String();
    }

    public function add(RequirementResult $check): void
    {
        $this->checks[$check->code] = $check;
    }

    public function eligible(): bool
    {
        return $this->checks !== [] && ! array_filter($this->checks, fn ($check) => $check->mandatory && $check->state !== RequirementState::Met);
    }

    public function allMet(): bool
    {
        return $this->checks !== [] && ! array_filter($this->checks, fn ($check) => $check->state !== RequirementState::Met);
    }

    public function reasons(): array
    {
        return array_values(array_map(fn ($check) => $check->message, array_filter($this->checks, fn ($check) => $check->mandatory && $check->state !== RequirementState::Met)));
    }

    public function toArray(): array
    {
        $result = ['eligible' => $this->eligible(), 'all_met' => $this->allMet(), 'checked_at' => $this->checkedAt, 'checks' => array_values(array_map(fn ($check) => $check->toArray(), $this->checks))];
        foreach (RequirementState::cases() as $state) {
            $result[$state->value] = array_keys(array_filter($this->checks, fn ($check) => $check->state === $state));
        }

        return $result;
    }
}
