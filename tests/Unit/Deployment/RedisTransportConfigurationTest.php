<?php

namespace Tests\Unit\Deployment;

use App\Support\Deployment\RedisTransportConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RedisTransportConfigurationTest extends TestCase
{
    public function test_deployed_connections_cannot_downgrade_to_plaintext(): void
    {
        foreach (['staging', 'production'] as $environment) {
            $policy = RedisTransportConfiguration::forEnvironment($environment, 'tcp', '10.1.0.3', '/trusted/ca.pem');
            self::assertSame('tls', $policy['scheme']);
            self::assertSame([
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => '10.1.0.3',
                'cafile' => '/trusted/ca.pem',
            ], $policy['context']['stream']);
        }
    }

    public function test_local_plaintext_connections_remain_supported(): void
    {
        self::assertSame(['scheme' => 'tcp', 'context' => null],
            RedisTransportConfiguration::forEnvironment('local', 'tcp', '127.0.0.1', null));
    }

    public function test_tls_without_an_explicit_ca_still_requires_peer_verification(): void
    {
        $policy = RedisTransportConfiguration::forEnvironment('local', 'tls', 'redis.example.org', null);
        self::assertTrue($policy['context']['stream']['verify_peer']);
        self::assertTrue($policy['context']['stream']['verify_peer_name']);
        self::assertArrayNotHasKey('cafile', $policy['context']['stream']);
    }

    public function test_empty_endpoint_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RedisTransportConfiguration::forEnvironment('staging', 'tls', '', null);
    }

    public function test_unknown_local_transport_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RedisTransportConfiguration::forEnvironment('local', 'udp', 'localhost', null);
    }
}