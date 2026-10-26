<?php

namespace App\Services\Integrations;

use App\Models\CitizenServiceRequest;
use Illuminate\Validation\ValidationException;

final readonly class VerificationContext
{
    public function __construct(public string $correlation, public string $kind, public array $subject, public string $fingerprint) {}

    public static function forRequest(CitizenServiceRequest $request, string $kind, array $rule, string $correlation, ?int $documentId = null): self
    {
        $request = $request->fresh(['citizen', 'service']);
        $subject = ['request_reference' => $request->reference];
        $evidence = [];
        if ($kind === 'identity') {
            $subject['national_id'] = $request->citizen->nationalIdentity();
        } elseif ($kind === 'document') {
            $doc = $documentId ? $request->citizen->documents()->where('document_type_code', $request->service->document_type)->find($documentId) : null;
            if ($doc) {
                $subject += ['document_number' => $doc->document_number, 'document_type' => $doc->document_type_code, 'issued_at' => $doc->issued_at?->toDateString(), 'expires_at' => $doc->expires_at?->toDateString()];
                $evidence = [$doc->id, $doc->status];
            }
        } elseif ($kind === 'medical') {
            $exam = $request->examinations()->where('user_id', $request->user_id)->where('government_service_id', $request->government_service_id)->latest('id')->first();
            if ($exam) {
                $subject += ['national_id' => $request->citizen->nationalIdentity(), 'examination_reference' => $exam->source_reference, 'examined_at' => $exam->examined_at?->toDateString()];
                $evidence = [$exam->id, $exam->result, $exam->expires_at?->toDateString(), $exam->rules_version];
            }
        } elseif ($kind === 'payment') {
            $charge = $request->charge()->with('payment')->first();
            if ($charge && $charge->user_id === $request->user_id && $charge->government_service_id === $request->government_service_id && $charge->amount === $request->service_fee && $charge->currency === $request->service_currency && $charge->payment && $charge->payment->amount === $charge->amount && $charge->payment->currency === $charge->currency) {
                $subject += ['transaction_reference' => $charge->payment->receipt_reference, 'amount' => $charge->amount, 'currency' => $charge->currency];
                $evidence = [$charge->id, $charge->payment->id, $charge->payment->paid_at?->toDateString()];
            }
        }
        $key = match ($kind) {
            'identity' => 'national_id','document' => 'document_number','medical' => 'examination_reference','payment' => 'transaction_reference',default => null
        };
        if (! $key || ! filled($subject[$key] ?? null)) {
            throw ValidationException::withMessages(['verification' => 'البيانات المحلية اللازمة للاستعلام الخارجي غير متوفرة.']);
        }
        // Digest cannot be used to recover citizen identifiers without the application secret.
        $fingerprint = hash_hmac('sha256', json_encode([$kind, $subject, $evidence, $rule, $request->service_rules_version, $request->service->rules_version, config('integrations.providers.'.$rule['provider'], [])], JSON_THROW_ON_ERROR), (string) config('app.key'));

        return new self($correlation, $kind, $subject, $fingerprint);
    }
}
