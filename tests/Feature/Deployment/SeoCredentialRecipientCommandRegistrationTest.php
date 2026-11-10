<?php

namespace Tests\Feature\Deployment;

use App\Console\Commands\InspectSeoCredentialRecipientCommand;
use App\Support\Deployment\Credentials\SeoCredentialRecipientInspector;
use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

final class SeoCredentialRecipientCommandRegistrationTest extends TestCase
{
    public function test_read_only_seo_recipient_inspection_is_registered(): void
    {
        $this->assertInstanceOf(
            SeoCredentialRecipientInspector::class,
            app(SeoCredentialRecipientInspector::class),
        );

        $commands = app(Kernel::class)->all();
        $this->assertArrayHasKey('engage:credential-recipient:inspect', $commands);
        $this->assertInstanceOf(
            InspectSeoCredentialRecipientCommand::class,
            $commands['engage:credential-recipient:inspect'],
        );
    }
}