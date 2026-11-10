<?php

namespace Tests\Feature\Deployment;

use App\Console\Commands\InspectSeoCredentialInstallationCommand;
use App\Support\Deployment\Credentials\SeoCredentialInstallationRegistry;
use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

final class SeoCredentialInstallationCommandRegistrationTest extends TestCase
{
    public function test_read_only_package_registered_installation_inspection_is_available(): void
    {
        self::assertInstanceOf(SeoCredentialInstallationRegistry::class, app(SeoCredentialInstallationRegistry::class));
        $commands = app(Kernel::class)->all();
        self::assertArrayHasKey('engage:credential-installation:inspect', $commands);
        self::assertInstanceOf(
            InspectSeoCredentialInstallationCommand::class,
            $commands['engage:credential-installation:inspect'],
        );
    }
}