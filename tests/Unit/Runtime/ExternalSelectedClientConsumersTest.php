<?php

namespace Tests\Unit\Runtime;

use App\Support\Clients\ClientEnvironmentLoader;
use App\Support\Deployment\Credentials\SeoSelectedCredentialEnvironment;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExternalSelectedClientConsumersTest extends TestCase
{
    private string $root;

    /** @var array<string, string|false> */
    private array $original = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/engage-seo-external-consumer-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/client/acme/config', 0700, true);
        mkdir($this->root.'/shared/runtime/config/r1', 0700, true);
        mkdir($this->root.'/shared/runtime/cache/c1', 0700, true);
        file_put_contents($this->root.'/client/acme/config/client_packages.php', "<?php return ['environment_keys' => []];\n");
        file_put_contents($this->root.'/client/acme/.env', "APP_URL=https://legacy.invalid\n");
        file_put_contents($this->generation().'/root.env', "APP_ENV=staging\nCLIENT_KEY=acme\n");
        file_put_contents($this->generation().'/client.env', "APP_URL=https://external.example.test\n");
        chmod($this->generation().'/root.env', 0600);
        chmod($this->generation().'/client.env', 0600);
        foreach (['ENGAGE_RUNTIME_CONFIG_DIR', 'APP_CONFIG_CACHE', 'CLIENT_KEY', 'APP_URL'] as $key) {
            $this->original[$key] = getenv($key);
        }
        putenv('ENGAGE_RUNTIME_CONFIG_DIR='.$this->generation());
        putenv('APP_CONFIG_CACHE='.$this->root.'/shared/runtime/cache/c1/config.php');
        putenv('CLIENT_KEY=acme');
        $_ENV['CLIENT_KEY'] = 'acme';
        $_SERVER['CLIENT_KEY'] = 'acme';
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        $this->deleteDirectory($this->root);
        parent::tearDown();
    }

    private function generation(): string
    {
        return $this->root.'/shared/runtime/config/r1';
    }

    public function test_bootstrap_and_deployment_credential_reader_use_the_same_external_client_file(): void
    {
        (new ClientEnvironmentLoader())->load($this->root);
        self::assertSame('https://external.example.test', getenv('APP_URL'));
        $values = (new SeoSelectedCredentialEnvironment())->read($this->root, 'acme');
        self::assertSame('https://external.example.test', $values['APP_URL']);
    }

    public function test_missing_external_file_blocks_even_with_legacy_file_present(): void
    {
        unlink($this->generation().'/client.env');
        $this->expectException(RuntimeException::class);
        (new SeoSelectedCredentialEnvironment())->read($this->root, 'acme');
    }

    public function test_unsafe_external_file_blocks_bootstrap_even_with_legacy_file_present(): void
    {
        chmod($this->generation().'/client.env', 0644);
        $this->expectException(RuntimeException::class);
        (new ClientEnvironmentLoader())->load($this->root);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.'/'.$item;
            is_dir($path) && ! is_link($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
