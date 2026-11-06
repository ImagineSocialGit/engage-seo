<?php

namespace Tests\Feature\Donations;

use App\Integrations\EngageCore\Exceptions\EngageCoreDonationsException;
use App\Integrations\EngageCore\Services\HttpEngageCoreDonationsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SiteDonationsTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('engage_core.donations', [
            'base_url' => 'https://webhooks.example.test',
            'client_id' => 'sample_site',
            'signing_secret' => str_repeat('s', 48),
        ]);
    }

    public function test_checkout_and_status_use_signed_server_to_server_requests(): void
    {
        Http::fake([
            'https://webhooks.example.test/external/donations/checkout' => Http::response([
                'data' => ['next_url' => 'https://checkout.stripe.com/c/pay/cs_test_example'],
            ], 201),
            'https://webhooks.example.test/external/donations/checkout-status/*' => Http::response([
                'data' => ['status' => 'confirmed', 'amount_cents' => 5000, 'currency' => 'USD'],
            ]),
        ]);

        $client = app(HttpEngageCoreDonationsClient::class);
        $url = $client->checkout([
            'external_id' => (string) Str::uuid(),
            'name' => 'Example Supporter',
            'email' => 'supporter@example.test',
            'amount_cents' => 5000,
            'verification' => ['provider' => 'turnstile', 'action' => 'donations', 'outcome' => 'passed'],
        ]);

        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_example', $url);
        $this->assertSame('confirmed', $client->status(str_repeat('a', 64))['status']);

        Http::assertSentCount(2);
        Http::assertSent(static fn ($request): bool => $request->method() === 'POST'
            && $request->hasHeader('X-Engage-Signature')
            && $request->hasHeader('X-Engage-Client', 'sample_site')
            && $request->url() === 'https://webhooks.example.test/external/donations/checkout');
    }

    public function test_invalid_api_response_is_not_redirected_to(): void
    {
        Http::fake(['*' => Http::response(['data' => ['next_url' => 'javascript:alert(1)']], 201)]);
        $this->expectException(EngageCoreDonationsException::class);
        app(HttpEngageCoreDonationsClient::class)->checkout(['external_id' => (string) Str::uuid()]);
    }
}