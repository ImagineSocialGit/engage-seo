<?php

namespace Tests\Feature\Foundation;

use App\Support\Seo\Migration\SeoMigrationAuditor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OldPlatformMigrationFoundationTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-old-platform-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists(
            $this->temporaryRoot.'/clients/migration-test-client/resources/migration'
        );

        config()->set('client.key', 'migration-test-client');
        config()->set('app.url', 'https://site.example.test');
        config()->set('site.seo.default_indexable', true);
        config()->set('site.seo.redirects', []);
        config()->set('seo_migration.enabled', true);
        config()->set(
            'seo_migration.inventory_path',
            'resources/migration/legacy-urls.tsv',
        );
        config()->set('pages', []);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_old_platform_migration_audit_command_is_registered(): void
    {
        $this->assertArrayHasKey(
            'seo:migration:audit',
            Artisan::all(),
        );
    }

    public function test_complete_inventory_passes_with_preserved_redirected_and_retired_urls(): void
    {
        config()->set('pages', [
            'preserved' => [
                'path' => '/preserved',
                'meta' => [
                    'indexable' => true,
                ],
                'sections' => [],
            ],
            'replacement' => [
                'path' => '/replacement',
                'meta' => [
                    'indexable' => true,
                ],
                'sections' => [],
            ],
        ]);

        config()->set('site.seo.redirects', [
            [
                'from' => '/old-page',
                'to' => '/replacement',
                'status' => 301,
            ],
        ]);

        $this->writeInventory([
            ['/preserved', 'preserved', '', ''],
            ['/old-page', 'redirected', '/replacement', ''],
            ['/obsolete', 'retired', '', 'No relevant replacement'],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertTrue($result->enabled);
        $this->assertTrue($result->valid());
        $this->assertSame([], $result->errors);
        $this->assertSame([
            'total' => 3,
            'preserved' => 1,
            'redirected' => 1,
            'retired' => 1,
            'unaccounted' => 0,
        ], $result->counts());
    }

    public function test_blank_outcome_is_unaccounted_and_fails_cutover_audit(): void
    {
        $this->writeInventory([
            ['/unreviewed', '', '', ''],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertFalse($result->valid());
        $this->assertSame(1, $result->counts()['unaccounted']);
        $this->assertNotEmpty($result->errors);
    }

    public function test_redirected_url_requires_matching_permanent_runtime_redirect(): void
    {
        config()->set('pages', [
            'replacement' => [
                'path' => '/replacement',
                'meta' => [
                    'indexable' => true,
                ],
                'sections' => [],
            ],
        ]);

        config()->set('site.seo.redirects', [
            [
                'from' => '/old-page',
                'to' => '/replacement',
                'status' => 302,
            ],
        ]);

        $this->writeInventory([
            ['/old-page', 'redirected', '/replacement', ''],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertFalse($result->valid());
        $this->assertNotEmpty($result->errors);
    }

    public function test_internal_destination_must_resolve_to_an_intended_indexable_page(): void
    {
        config()->set('pages', [
            'replacement' => [
                'path' => '/replacement',
                'meta' => [
                    'indexable' => false,
                ],
                'sections' => [],
            ],
        ]);

        config()->set('site.seo.redirects', [
            [
                'from' => '/old-page',
                'to' => '/replacement',
                'status' => 301,
            ],
        ]);

        $this->writeInventory([
            ['/old-page', 'redirected', '/replacement', ''],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertFalse($result->valid());
        $this->assertNotEmpty($result->errors);
    }

    public function test_retired_url_must_not_still_render_or_redirect(): void
    {
        config()->set('pages', [
            'still-present' => [
                'path' => '/retired',
                'meta' => [
                    'indexable' => true,
                ],
                'sections' => [],
            ],
        ]);

        $this->writeInventory([
            ['/retired', 'retired', '', 'Deliberately removed'],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertFalse($result->valid());
        $this->assertNotEmpty($result->errors);
    }

    public function test_duplicate_legacy_paths_are_inventory_errors(): void
    {
        $this->writeInventory([
            ['/duplicate', '', '', ''],
            ['/duplicate/', '', '', ''],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertFalse($result->valid());
        $this->assertNotEmpty($result->errors);
    }

    public function test_external_redirect_target_is_allowed_with_local_verification_warning(): void
    {
        config()->set('site.seo.redirects', [
            [
                'from' => '/old-domain-path',
                'to' => 'https://replacement.example.test/new-path',
                'status' => 301,
            ],
        ]);

        $this->writeInventory([
            [
                '/old-domain-path',
                'redirected',
                'https://replacement.example.test/new-path',
                '',
            ],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertTrue($result->valid());
        $this->assertNotEmpty($result->warnings);
    }

    public function test_disabled_migration_does_not_require_an_inventory_file(): void
    {
        config()->set('seo_migration.enabled', false);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertFalse($result->enabled);
        $this->assertTrue($result->valid());
        $this->assertSame([], $result->entries);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function writeInventory(array $rows): void
    {
        $lines = [
            implode("\t", ['path', 'outcome', 'target', 'notes']),
        ];

        foreach ($rows as $row) {
            $lines[] = implode("\t", $row);
        }

        File::put(
            $this->temporaryRoot
                .'/clients/migration-test-client/resources/migration/legacy-urls.tsv',
            implode(PHP_EOL, $lines).PHP_EOL,
        );
    }
}