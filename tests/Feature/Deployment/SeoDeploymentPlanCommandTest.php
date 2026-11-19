<?php

namespace Tests\Feature\Deployment;

use App\Console\Commands\InspectSeoDeploymentPlanCommand;
use App\Support\Deployment\SeoDeploymentPlanRegistry;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SeoDeploymentPlanCommandTest extends TestCase
{
    public function test_read_only_command_is_registered(): void
    {
        self::assertInstanceOf(SeoDeploymentPlanRegistry::class, app(SeoDeploymentPlanRegistry::class));
        self::assertInstanceOf(InspectSeoDeploymentPlanCommand::class,
            Artisan::all()['engage:deployment-plan'] ?? null);
    }

    public function test_wrong_client_fails_without_disclosing_runtime_data(): void
    {
        $rc = Artisan::call('engage:deployment-plan', [
            '--json' => true,
            '--expected-client' => '../other-client',
            '--expected-environment' => 'staging',
        ]);
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $rc);
        self::assertSame('blocked', $report['status']);
        self::assertFalse($report['writes_allowed']);
        self::assertArrayNotHasKey('environment_requirements', $report);
    }
}
