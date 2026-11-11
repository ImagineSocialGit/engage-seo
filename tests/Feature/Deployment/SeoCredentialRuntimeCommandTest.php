<?php

namespace Tests\Feature\Deployment;

use App\Console\Commands\InspectSeoCredentialRuntimeCommand;
use App\Support\Deployment\Credentials\SeoCredentialRuntimeProbe;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SeoCredentialRuntimeCommandTest extends TestCase
{
    public function test_probe_and_console_command_are_registered(): void
    {
        self::assertInstanceOf(SeoCredentialRuntimeProbe::class, app(SeoCredentialRuntimeProbe::class));
        self::assertInstanceOf(InspectSeoCredentialRuntimeCommand::class,
            Artisan::all()['engage:credential-runtime:inspect'] ?? null);
    }

    public function test_wrong_selected_client_returns_redacted_failure_without_proof(): void
    {
        $code = Artisan::call('engage:credential-runtime:inspect', [
            '--inventory' => true,
            '--expected-client' => '../unselected-client',
            '--expected-environment' => 'local',
        ]);
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $code);
        self::assertSame('blocked', $report['status']);
        self::assertSame('target_selection', $report['phase']);
        self::assertSame(['runtime_target_identity_mismatch'], $report['findings']);
        self::assertArrayNotHasKey('proof', $report);
        self::assertFalse($report['writes_allowed']);
    }
}