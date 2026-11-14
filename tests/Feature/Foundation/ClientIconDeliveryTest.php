<?php

namespace Tests\Feature\Foundation;

use App\Http\Controllers\Seo\ClientIconController;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ClientIconDeliveryTest extends TestCase
{
    private ?string $temporaryRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir()
            .'/engage-seo-client-icons-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($this->temporaryRoot);

        $this->app->bind(
            ClientIconController::class,
            fn (): ClientIconController => new ClientIconController($this->temporaryRoot),
        );
    }

    protected function tearDown(): void
    {
        if ($this->temporaryRoot !== null) {
            File::deleteDirectory($this->temporaryRoot);
        }

        parent::tearDown();
    }

    public function test_selected_clients_get_only_their_own_favicons(): void
    {
        $this->createIcon('foundation-one', 'first client icon');
        $this->createIcon('foundation-two', 'second client icon');

        config()->set('client.key', 'foundation-one');
        $one = $this->get('/site-icons/favicon.svg');
        $one->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertSame(
            'first client icon',
            file_get_contents($one->baseResponse->getFile()->getPathname()),
        );

        config()->set('client.key', 'foundation-two');
        $two = $this->get('/site-icons/favicon.svg');
        $two->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertSame(
            'second client icon',
            file_get_contents($two->baseResponse->getFile()->getPathname()),
        );
    }

    public function test_missing_client_and_unsupported_names_are_not_served(): void
    {
        $this->createIcon('foundation-one', 'first client icon');

        config()->set('client.key', null);
        $this->get('/site-icons/favicon.svg')->assertNotFound();

        config()->set('client.key', 'foundation-one');
        $this->get('/site-icons/private.txt')->assertNotFound();
        $this->get('/site-icons/unknown.svg')->assertNotFound();
        $this->get('/site-icons/favicon.ico')->assertNotFound();
    }

    private function createIcon(string $client, string $contents): void
    {
        $directory = $this->temporaryRoot
            .'/client/'.$client.'/resources/favicons';

        File::ensureDirectoryExists($directory);
        File::put($directory.'/favicon.svg', $contents);
    }
}