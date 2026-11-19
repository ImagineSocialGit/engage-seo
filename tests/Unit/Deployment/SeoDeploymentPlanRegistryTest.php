<?php

namespace Tests\Unit\Deployment;

use App\Support\Deployment\SeoDeploymentPlanRegistry;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SeoDeploymentPlanRegistryTest extends TestCase
{
    public function test_missing_provider_fails_closed_and_provider_opt_out_is_explicit(): void
    {
        $registry = new SeoDeploymentPlanRegistry();
        $report = $registry->inspect(['Sample\\Provider'], []);
        self::assertFalse($report['declared_complete']);
        self::assertSame(['Sample\\Provider'], $report['missing_provider_contributors']);
        $registry->register('Sample\\Provider', 'sample');
        $report = $registry->inspect(['Sample\\Provider'], []);
        self::assertTrue($report['declared_complete']);
        self::assertSame([], $report['environment_requirements']);
    }

    public function test_contribution_is_typed_and_has_no_secret_values(): void
    {
        $registry = new SeoDeploymentPlanRegistry();
        $registry->register('Sample\\Provider', 'sample',
            [['key' => 'SAMPLE_API_SECRET', 'secret' => true]],
            ['sample.configure'], ['sample.callback']);
        $report = $registry->inspect(['Sample\\Provider'], ['forms']);
        self::assertTrue($report['declared_complete']);
        self::assertSame('unverified', $report['environment_requirements'][0]['status']);
        self::assertSame('SAMPLE_API_SECRET', $report['environment_requirements'][0]['key']);
        self::assertFalse($report['runtime_verified']);
        self::assertArrayNotHasKey('value', $report['environment_requirements'][0]);
    }

    public function test_duplicate_owner_and_environment_keys_rejected(): void
    {
        $registry = new SeoDeploymentPlanRegistry();
        $registry->register('Sample\\First', 'same', [['key' => 'SAMPLE_API_SECRET', 'secret' => true]]);
        $registry->register('Sample\\Second', 'same');
        $this->expectException(LogicException::class);
        $registry->inspect(['Sample\\First', 'Sample\\Second'], []);
    }

    public function test_invalid_or_duplicate_registration_rejected(): void
    {
        $registry = new SeoDeploymentPlanRegistry();
        $registry->register('Sample\\Provider', 'sample');
        $this->expectException(InvalidArgumentException::class);
        $registry->register('Sample\\Provider', 'sample');
    }
}
