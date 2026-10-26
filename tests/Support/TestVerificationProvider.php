<?php

namespace Tests\Support;

use App\Contracts\IntegrationProvider;
use App\Enums\VerificationStatus;
use App\Services\Integrations\IntegrationTransport;
use App\Services\Integrations\VerificationContext;
use App\Services\Integrations\VerificationResult;

/** TEST CONTRACT ONLY: not a real provider schema. Never registered in runtime config. */
class TestVerificationProvider implements IntegrationProvider
{
    public function verify(VerificationContext $context, array $settings): VerificationResult
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Test provider prohibited');
        }
        [$response,$attempts] = app(IntegrationTransport::class)->query($settings, $context->kind, ['correlation' => $context->correlation, 'subject' => $context->subject]);
        if ($response->status() === 429 || $response->serverError()) {
            return new VerificationResult(VerificationStatus::Unavailable, httpStatus: $response->status(), attempts: $attempts);
        }
        if (! $response->successful()) {
            return new VerificationResult(VerificationStatus::Failed, httpStatus: $response->status(), attempts: $attempts);
        }
        $data = $response->json();
        if (! is_array($data) || ($data['correlation'] ?? null) !== $context->correlation || ($data['subject'] ?? null) !== $context->subject) {
            return new VerificationResult(VerificationStatus::Unknown, httpStatus: $response->status(), attempts: $attempts);
        }
        $state = is_string($data['status'] ?? null) ? VerificationStatus::tryFrom($data['status']) : null;

        return new VerificationResult($state ?? VerificationStatus::Unknown, true, $response->status(), $attempts);
    }
}
