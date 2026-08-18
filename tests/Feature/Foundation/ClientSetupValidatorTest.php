<?php

namespace Tests\Feature\Foundation;

use App\Support\SetupValidation\ClientSetupValidator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClientSetupValidatorTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-setup-validator-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->temporaryRoot.'/clients/validator-client/config/pages');
        File::ensureDirectoryExists($this->temporaryRoot.'/clients/validator-client/resources/views');
        File::ensureDirectoryExists($this->temporaryRoot.'/clients/validator-client/resources/images/raw');

        File::put(
            $this->temporaryRoot.'/clients/validator-client/config/client.php',
            "<?php\n\nreturn [\n    'key' => 'validator-client',\n];\n"
        );
        File::put(
            $this->temporaryRoot.'/clients/validator-client/config/features.php',
            "<?php\n\nreturn [];\n"
        );
        File::put(
            $this->temporaryRoot.'/clients/validator-client/config/site.php',
            "<?php\n\nreturn [\n    'name' => 'Validator Test Site',\n];\n"
        );
        File::put(
            $this->temporaryRoot.'/clients/validator-client/.env.example',
            "APP_URL=\nDB_DATABASE=\nDB_USERNAME=\nDB_PASSWORD=\n"
        );

        config()->set('client.key', 'validator-client');
        config()->set('client.timezone', 'America/Chicago');
        config()->set('client.vertical', null);
        config()->set('verticals.available', []);
        config()->set('features.available', []);
        config()->set('features.enabled', []);
        config()->set('features.disabled', []);
        config()->set('pages', []);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_valid_selected_client_setup_passes_validation(): void
    {
        File::put(
            $this->temporaryRoot.'/clients/validator-client/.env',
            implode(PHP_EOL, [
                'APP_URL=https://validator.example.test',
                'DB_DATABASE=validator_database',
                'DB_USERNAME=validator_user',
                'DB_PASSWORD=',
                'CACHE_PREFIX=validator-cache-',
                'REDIS_PREFIX=validator-redis-',
                '',
            ])
        );

        $result = app(ClientSetupValidator::class)->validate(
            $this->temporaryRoot
        );

        $this->assertTrue($result->valid());
        $this->assertSame([], $result->errors);
    }

    public function test_root_client_owned_values_and_unsupported_client_values_fail_validation(): void
    {
        File::put(
            $this->temporaryRoot.'/.env',
            "APP_URL=https://root.example.test\n"
        );

        File::put(
            $this->temporaryRoot.'/clients/validator-client/.env',
            implode(PHP_EOL, [
                'APP_URL=https://validator.example.test',
                'DB_DATABASE=validator_database',
                'DB_USERNAME=validator_user',
                'DB_PASSWORD=',
                'APP_ENV=production',
                '',
            ])
        );

        $result = app(ClientSetupValidator::class)->validate(
            $this->temporaryRoot
        );

        $this->assertFalse($result->valid());
        $this->assertCount(2, $result->errors);
    }

    public function test_invalid_page_configuration_fails_validation(): void
    {
        File::put(
            $this->temporaryRoot.'/clients/validator-client/.env',
            implode(PHP_EOL, [
                'APP_URL=https://validator.example.test',
                'DB_DATABASE=validator_database',
                'DB_USERNAME=validator_user',
                'DB_PASSWORD=',
                '',
            ])
        );

        config()->set('pages', [
            'invalid' => [
                'path' => 'missing-leading-slash',
                'sections' => [],
            ],
        ]);

        $result = app(ClientSetupValidator::class)->validate(
            $this->temporaryRoot
        );

        $this->assertFalse($result->valid());
        $this->assertCount(1, $result->errors);
    }

    public function test_invalid_site_shell_configuration_fails_validation(): void
    {
        File::put(
            $this->temporaryRoot.'/clients/validator-client/.env',
            implode(PHP_EOL, [
                'APP_URL=https://validator.example.test',
                'DB_DATABASE=validator_database',
                'DB_USERNAME=validator_user',
                'DB_PASSWORD=',
                '',
            ])
        );

        config()->set('site.shell.navigation.items', [
            [
                'label' => 'Invalid item',
            ],
        ]);

        $result = app(ClientSetupValidator::class)->validate(
            $this->temporaryRoot
        );

        $this->assertFalse($result->valid());
        $this->assertCount(1, $result->errors);
    }

    public function test_setup_validate_command_fails_when_no_client_is_selected(): void
    {
        config()->set('client.key', null);

        $this->artisan('setup:validate')
            ->assertExitCode(1);
    }
}