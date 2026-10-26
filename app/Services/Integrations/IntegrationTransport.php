<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class IntegrationTransport
{
    /** No response mapping is assumed: only a documented provider adapter interprets responses. */
    public function query(array $settings, string $kind, array $payload): array
    {
        $base = $settings['base_url'] ?? '';
        $parts = parse_url($base);
        $path = $settings['endpoints'][$kind] ?? '';
        $host = $parts['host'] ?? '';
        if (($parts['scheme'] ?? '') !== 'https' || ! $host || $host !== ($settings['allowed_host'] ?? null) || filter_var($host, FILTER_VALIDATE_IP) || in_array(strtolower($host), ['localhost', 'metadata.google.internal'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) || ! preg_match('~^/[a-zA-Z0-9/_-]+$~D', $path)) {
            throw new \InvalidArgumentException('Unapproved provider endpoint');
        }
        $tries = max(0, min(2, (int) ($settings['retries'] ?? 0))) + 1;
        for ($i = 1; $i <= $tries; $i++) {
            $client = Http::acceptJson()->asJson()->withOptions(['allow_redirects' => false, 'verify' => true])->connectTimeout(max(1, min(5, (int) ($settings['connect_timeout'] ?? config('integrations.connect_timeout')))))->timeout(max(1, min(15, (int) ($settings['timeout'] ?? config('integrations.timeout')))));
            // Authentication mapping belongs to the adapter. This helper supports bearer only when explicitly selected.
            if (($settings['auth'] ?? null) === 'bearer') {
                $client = $client->withToken($settings['token']);
            }
            $response = $client->post(rtrim($base, '/').$path, $payload);
            if ($i < $tries && ($response->status() === 429 || $response->serverError())) {
                usleep(100000 * $i);

                continue;
            }

            return [$response, $i];
        }
        throw new \LogicException('Unreachable');
    }
}
