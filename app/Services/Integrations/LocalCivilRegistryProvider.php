<?php

namespace App\Services\Integrations;

use App\Contracts\IntegrationProvider;
use App\Enums\VerificationStatus;

class LocalCivilRegistryProvider implements IntegrationProvider
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('civil_registry_demo.enabled') === true;
    }

    public function simulate(string $nationalId, string $name): string
    {
        abort_unless(self::enabled(), 404);
        $record = config('civil_registry_demo.records', [])[$nationalId] ?? null;
        if (! $record) {
            return 'not_found';
        }
        if (! $record['available']) {
            return 'unavailable';
        }
        $normalize = fn (string $value) => preg_replace('/\s+/u', ' ', trim($value));

        return $normalize($name) === $normalize($record['name']) ? 'match' : 'mismatch';
    }

    // A demo must never satisfy an official integration requirement, even if misconfigured.
    public function verify(VerificationContext $context, array $settings): VerificationResult
    {
        return new VerificationResult(VerificationStatus::Unknown);
    }
}
