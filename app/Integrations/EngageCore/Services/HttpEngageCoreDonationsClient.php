<?php

namespace App\Integrations\EngageCore\Services;

use App\Integrations\EngageCore\Exceptions\EngageCoreDonationsException;
use App\Integrations\EngageCore\Support\EngageCoreRequestSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

final class HttpEngageCoreDonationsClient
{
    public function __construct(private readonly EngageCoreRequestSigner $signer) {}

    /** @param array<string, mixed> $payload */
    public function checkout(array $payload): string
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new EngageCoreDonationsException('invalid_request');
        }

        $response = $this->request('POST', '/external/donations/checkout', $body);
        $this->assertSuccessful($response);
        $nextUrl = $response->json('data.next_url');

        if (! is_string($nextUrl) || ! $this->validCheckoutUrl($nextUrl)) {
            throw new EngageCoreDonationsException('invalid_response');
        }

        return $nextUrl;
    }

    /** @return array{status:string, amount_cents:int, currency:string} */
    public function status(string $token): array
    {
        if (preg_match('/^[A-Za-z0-9]{64}$/D', $token) !== 1) {
            throw new EngageCoreDonationsException('invalid_request');
        }

        $response = $this->request('GET', '/external/donations/checkout-status/'.$token, '');
        $this->assertSuccessful($response);
        $status = $response->json('data.status');
        $amount = $response->json('data.amount_cents');
        $currency = $response->json('data.currency');

        if (! in_array($status, ['confirmed', 'pending'], true)
            || ! is_int($amount) || $amount < 1 || $currency !== 'USD'
        ) {
            throw new EngageCoreDonationsException('invalid_response');
        }

        return ['status' => $status, 'amount_cents' => $amount, 'currency' => $currency];
    }

    private function request(string $method, string $path, string $body): Response
    {
        $base = rtrim(trim((string) config('engage_core.donations.base_url')), '/');
        $id = trim((string) config('engage_core.donations.client_id'));
        $secret = (string) config('engage_core.donations.signing_secret');
        $parsed = parse_url($base);

        if (! is_array($parsed)
            || ! in_array($parsed['scheme'] ?? null, ['http', 'https'], true)
            || ! is_string($parsed['host'] ?? null) || $parsed['host'] === ''
            || ! in_array($parsed['path'] ?? '', ['', '/'], true)
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parsed)) !== []
            || ($parsed['scheme'] !== 'https' && ! app()->environment('local', 'testing'))
            || preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $id) !== 1
            || strlen($secret) < 32
        ) {
            throw new EngageCoreDonationsException('configuration');
        }

        // A new nonce on each attempt, while the durable form UUID stays fixed.
        // Retrying a successful Core write is safe through contribution.entry_key.
        $attempts = 2;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $headers = $this->signer->headers($method, $path, $id, $secret, $body);
            try {
                $pending = Http::acceptJson()->withHeaders($headers)
                    ->connectTimeout(2)->timeout(10);
                $response = $method === 'GET'
                    ? $pending->get($base.$path)
                    : $pending->withBody($body, 'application/json')->post($base.$path);
            } catch (ConnectionException) {
                if ($attempt === $attempts) {
                    throw new EngageCoreDonationsException('unavailable');
                }
                continue;
            }

            if (! $response->serverError() || $attempt === $attempts) {
                return $response;
            }
        }

        throw new EngageCoreDonationsException('unavailable');
    }

    private function assertSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $kind = match ($response->status()) {
            401, 403 => 'authentication',
            404 => 'unavailable',
            409 => 'conflict',
            422 => 'validation',
            429 => 'rate_limited',
            default => 'unavailable',
        };
        $fields = $response->json('error.fields');
        $normalized = [];
        if ($kind === 'validation' && is_array($fields)) {
            foreach ($fields as $key => $messages) {
                if (is_string($key) && is_array($messages)) {
                    $normalized[$key] = array_values(array_filter($messages, 'is_string'));
                }
            }
        }

        throw new EngageCoreDonationsException($kind, $normalized);
    }

    private function validCheckoutUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! is_array($parsed) || $parsed['scheme'] !== 'https'
            || ! is_string($parsed['host'] ?? null) || $parsed['host'] === ''
            || array_intersect(['user', 'pass'], array_keys($parsed)) !== []
        ) {
            // The only HTTP exception is SEO's own local return URL after an
            // already-settled idempotent replay, never an arbitrary HTTP site.
            $origin = rtrim((string) config('app.url'), '/');
            return app()->environment('local', 'testing')
                && str_starts_with($url, $origin.'/_site-donations/return/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}