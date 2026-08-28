<?php

namespace Tests\Feature\Foundation;

use App\Support\Clients\ClientPackageLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ClientPackageFoundationTest extends TestCase
{
    private ?string $temporaryRoot = null;

    protected function tearDown(): void
    {
        unset($GLOBALS['engage_seo_client_package_autoloaded']);

        if ($this->temporaryRoot !== null) {
            File::deleteDirectory($this->temporaryRoot);
        }

        parent::tearDown();
    }

    public function test_client_without_composer_json_requires_no_nested_vendor_tree(): void
    {
        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-client-package-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists(
            $this->temporaryRoot.'/client/fixture'
        );

        app(ClientPackageLoader::class)->loadAutoloader(
            'fixture',
            $this->temporaryRoot,
        );

        $this->assertFalse(
            $GLOBALS['engage_seo_client_package_autoloaded'] ?? false
        );
    }

    public function test_client_composer_autoloader_is_loaded_only_when_client_declares_composer_packages(): void
    {
        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-client-package-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists(
            $this->temporaryRoot.'/client/fixture/vendor'
        );

        File::put(
            $this->temporaryRoot.'/client/fixture/composer.json',
            '{}'
        );

        File::put(
            $this->temporaryRoot.'/client/fixture/vendor/autoload.php',
            <<<'AUTOLOAD'
<?php
$GLOBALS['engage_seo_client_package_autoloaded'] = true;
AUTOLOAD
        );

        app(ClientPackageLoader::class)->loadAutoloader(
            'fixture',
            $this->temporaryRoot,
        );

        $this->assertTrue(
            $GLOBALS['engage_seo_client_package_autoloaded'] ?? false
        );
    }

    public function test_missing_client_vendor_autoloader_has_actionable_failure(): void
    {
        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-client-package-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists(
            $this->temporaryRoot.'/client/fixture'
        );

        File::put(
            $this->temporaryRoot.'/client/fixture/composer.json',
            '{}'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Run composer install inside client/fixture.');

        app(ClientPackageLoader::class)->loadAutoloader(
            'fixture',
            $this->temporaryRoot,
        );
    }

    public function test_client_package_providers_register_through_explicit_config(): void
    {
        config()->set('client_packages.providers', [
            FixtureClientPackageProvider::class,
        ]);

        app(ClientPackageLoader::class)->registerProviders();

        $this->assertSame(
            'registered',
            app('fixture-client-package-binding')
        );
    }

    public function test_client_package_provider_config_must_be_a_list_of_service_providers(): void
    {
        config()->set('client_packages.providers', [
            'not-a-provider' => FixtureClientPackageProvider::class,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(ClientPackageLoader::class)->providerClasses();
    }
}

final class FixtureClientPackageProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance(
            'fixture-client-package-binding',
            'registered',
        );
    }
}