<?php

namespace Tests\Unit\Deployment;

use App\Support\Deployment\Credentials\SeoSelectedCredentialEnvironment;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeoSelectedCredentialEnvironmentTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/seo-installation-fixture-'.bin2hex(random_bytes(10));
        mkdir($this->root.'/client/fixture-seo/config', 0700, true);
        file_put_contents($this->root.'/client/fixture-seo/config/client_packages.php', <<<'SOURCE'
<?php
return ['environment_keys' => [
    'ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID',
    'ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET',
    'ENGAGE_SEO_CORE_DONATIONS_URL',
]];
SOURCE
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            $file = $this->root.'/client/fixture-seo/.env';
            if (is_link($file) || is_file($file)) {
                unlink($file);
            }
            unlink($this->root.'/client/fixture-seo/config/client_packages.php');
            rmdir($this->root.'/client/fixture-seo/config');
            rmdir($this->root.'/client/fixture-seo');
            rmdir($this->root.'/client');
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function write(string $value): void
    {
        file_put_contents($this->root.'/client/fixture-seo/.env', $value);
        chmod($this->root.'/client/fixture-seo/.env', 0600);
    }

    public function test_missing_file_is_an_empty_first_provisioning_snapshot(): void
    {
        self::assertSame([], (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo'));
    }

    public function test_private_selected_client_file_is_read_only_and_kept_private(): void
    {
        $this->write("APP_URL=http://fixture-seo.test\nENGAGE_SEO_CORE_DONATIONS_CLIENT_ID=seo_donations\n");
        $data = (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
        self::assertSame('seo_donations', $data['ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID']);
        self::assertSame('http://fixture-seo.test', $data['APP_URL']);
        self::assertSame(0600, fileperms($this->root.'/client/fixture-seo/.env') & 0777);
    }

    public function test_group_readable_runtime_file_preserves_its_mode_group_and_contents(): void
    {
        $this->write("APP_URL=http://fixture-seo.test\nENGAGE_SEO_CORE_DONATIONS_CLIENT_ID=fixture-caller\n");
        $file = $this->root.'/client/fixture-seo/.env';
        chmod($file, 0640);
        clearstatcache(true, $file);
        $before = lstat($file);
        $bytes = file_get_contents($file);
        $values = (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
        self::assertSame('fixture-caller', $values['ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID']);
        clearstatcache(true, $file);
        $after = lstat($file);
        self::assertSame(0640, $after['mode'] & 07777);
        self::assertSame($before['gid'], $after['gid']);
        self::assertSame($before['ino'], $after['ino']);
        self::assertSame($bytes, file_get_contents($file));
    }

    public function test_world_read_and_group_write_modes_are_rejected_without_changes(): void
    {
        $this->write("APP_URL=http://fixture-seo.test\n");
        $file = $this->root.'/client/fixture-seo/.env';
        foreach ([0644, 0660, 0664, 0666] as $mode) {
            chmod($file, $mode);
            clearstatcache(true, $file);
            try {
                (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
                self::fail('Unsafe runtime access must be rejected.');
            } catch (RuntimeException) {
                self::assertSame($mode, fileperms($file) & 07777);
            }
        }
    }

    public function test_hard_linked_group_readable_environment_is_rejected(): void
    {
        $this->write("APP_URL=http://fixture-seo.test\n");
        $file = $this->root.'/client/fixture-seo/.env';
        chmod($file, 0640);
        $link = $this->root.'/linked.env';
        link($file, $link);
        clearstatcache(true, $file);
        try {
            $this->expectException(RuntimeException::class);
            (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
        } finally {
            unlink($link);
        }
    }

    public function test_duplicate_key_is_rejected(): void
    {
        $this->write("ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID=x\nENGAGE_SEO_CORE_DONATIONS_CLIENT_ID=y\n");
        $this->expectException(RuntimeException::class);
        (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
    }

    public function test_root_owned_key_is_rejected(): void
    {
        $this->write("APP_KEY=should-never-be-in-selected-client\n");
        $this->expectException(RuntimeException::class);
        (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
    }

    public function test_insecure_mode_is_rejected_not_fixed(): void
    {
        $this->write("ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID=x\n");
        chmod($this->root.'/client/fixture-seo/.env', 0644);
        $this->expectException(RuntimeException::class);
        (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
    }

    public function test_symlinked_environment_file_is_rejected(): void
    {
        $outside = $this->root.'/private.env';
        file_put_contents($outside, "ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID=x\n");
        chmod($outside, 0600);
        symlink($outside, $this->root.'/client/fixture-seo/.env');
        try {
            $this->expectException(RuntimeException::class);
            (new SeoSelectedCredentialEnvironment())->read($this->root, 'fixture-seo');
        } finally {
            unlink($outside);
        }
    }
}