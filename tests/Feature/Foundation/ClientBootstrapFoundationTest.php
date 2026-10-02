<?php

namespace Tests\Feature\Foundation;

use App\Contracts\Clients\ClientBootstrapContributor;
use App\Support\Clients\ClientBootstrapRegistry;
use App\Support\Clients\ClientBootstrapService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;
use Tests\DatabaseTestCase;

final class ClientBootstrapFoundationTest extends DatabaseTestCase
{
    private string $clientKey = 'bootstrap-foundation-client';

    private bool $createdBootstrapTrackingTable = false;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('client.key', $this->clientKey);

        if (! Schema::hasTable('client_bootstrap_runs')) {
            $migration = require base_path(
                'database/migrations/2026_10_02_000003_create_client_bootstrap_runs_table.php'
            );
            $migration->up();

            $this->createdBootstrapTrackingTable = true;
        }

        DB::table('client_bootstrap_runs')
            ->where('client_key', $this->clientKey)
            ->delete();

        Schema::dropIfExists('bootstrap_fixture_rows');

        Schema::create('bootstrap_fixture_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });

        File::ensureDirectoryExists(
            base_path("client/{$this->clientKey}/bootstrap")
        );

        app(ClientBootstrapRegistry::class)->register(
            BootstrapFixtureContributor::class
        );
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('client_bootstrap_runs')) {
            DB::table('client_bootstrap_runs')
                ->where('client_key', $this->clientKey)
                ->delete();
        }

        Schema::dropIfExists('bootstrap_fixture_rows');

        if ($this->createdBootstrapTrackingTable) {
            Schema::dropIfExists('client_bootstrap_runs');
        }

        File::deleteDirectory(
            base_path("client/{$this->clientKey}")
        );

        parent::tearDown();
    }

    public function test_selected_client_bootstrap_applies_once_then_skips_identical_source(): void
    {
        $this->writeBootstrapSource(
            <<<'PHP'
<?php

return [
    'version' => 1,
    'data' => [
        'value' => 'Initial state',
    ],
];
PHP
        );

        $first = app(ClientBootstrapService::class)->run();
        $second = app(ClientBootstrapService::class)->run();

        $this->assertSame('applied', $first[0]['status']);
        $this->assertSame('skipped', $second[0]['status']);
        $this->assertSame(1, DB::table('bootstrap_fixture_rows')->count());
        $this->assertSame(
            'Initial state',
            DB::table('bootstrap_fixture_rows')->value('value'),
        );
        $this->assertSame(1, DB::table('client_bootstrap_runs')->count());
    }

    public function test_applied_bootstrap_source_is_immutable(): void
    {
        $this->writeBootstrapSource(
            <<<'PHP'
<?php

return [
    'version' => 1,
    'data' => [
        'value' => 'Initial state',
    ],
];
PHP
        );

        app(ClientBootstrapService::class)->run();

        $this->writeBootstrapSource(
            <<<'PHP'
<?php

return [
    'version' => 2,
    'data' => [
        'value' => 'Replacement state',
    ],
];
PHP
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Initial bootstrap state is immutable after application'
        );

        app(ClientBootstrapService::class)->run();
    }

    public function test_unregistered_bootstrap_source_is_rejected_before_mutation(): void
    {
        File::put(
            base_path("client/{$this->clientKey}/bootstrap/unknown.php"),
            <<<'PHP'
<?php

return [
    'version' => 1,
    'data' => [],
];
PHP
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'has no registered contributor'
        );

        app(ClientBootstrapService::class)->run();
    }

    private function writeBootstrapSource(string $source): void
    {
        File::put(
            base_path("client/{$this->clientKey}/bootstrap/fixture.php"),
            $source,
        );
    }
}

final class BootstrapFixtureContributor implements ClientBootstrapContributor
{
    public function key(): string
    {
        return 'fixture';
    }

    public function validationErrors(array $payload): array
    {
        return isset($payload['value'])
            && is_string($payload['value'])
            && trim($payload['value']) !== ''
                ? []
                : ['Fixture bootstrap payload requires a non-blank value.'];
    }

    public function apply(array $payload): void
    {
        DB::table('bootstrap_fixture_rows')->insert([
            'value' => $payload['value'],
        ]);
    }
}