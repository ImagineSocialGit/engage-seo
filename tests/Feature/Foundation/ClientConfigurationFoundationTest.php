<?php

namespace Tests\Feature\Foundation;

use App\Support\Clients\ClientConfigLoader;
use App\Support\Features\FeatureManager;
use App\Support\Verticals\VerticalManager;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class ClientConfigurationFoundationTest extends TestCase
{
    protected string $testClientKey = 'foundation-test-client';

    protected function tearDown(): void
    {
        File::deleteDirectory(
            base_path("client/{$this->testClientKey}")
        );

        parent::tearDown();
    }

    public function test_client_config_overrides_platform_config(): void
    {
        $clientDirectory = base_path(
            "client/{$this->testClientKey}/config"
        );

        File::ensureDirectoryExists($clientDirectory);

        File::put(
            $clientDirectory.'/client.php',
            <<<'PHP'
<?php

return [
    'name' => 'Foundation Test Client',
    'key' => 'foundation-test-client',
    'timezone' => 'America/Chicago',
    'vertical' => null,
];
PHP
        );

        File::put(
            $clientDirectory.'/features.php',
            <<<'PHP'
<?php

return [
    'enabled' => [
        'blog',
    ],
];
PHP
        );

        config()->set('features.available', [
            'blog' => [
                'provider' => null,
            ],
        ]);

        app(ClientConfigLoader::class)->load($this->testClientKey);

        $this->assertSame(
            'Foundation Test Client',
            config('client.name')
        );
        $this->assertSame(
            'foundation-test-client',
            config('client.key')
        );
        $this->assertSame(
            'America/Chicago',
            config('client.timezone')
        );
        $this->assertSame(
            ['blog'],
            config('features.enabled')
        );
    }

    public function test_vertical_defaults_and_client_feature_selection_compose(): void
    {
        config()->set('client.vertical', 'construction');

        config()->set('verticals.available', [
            'construction' => [
                'name' => 'Construction',
                'default_features' => [
                    'services',
                ],
            ],
        ]);

        config()->set('features.available', [
            'services' => [
                'provider' => null,
            ],
            'blog' => [
                'provider' => null,
            ],
        ]);

        config()->set('features.enabled', [
            'blog',
        ]);

        config()->set('features.disabled', []);

        $manager = new FeatureManager(
            app(),
            new VerticalManager()
        );

        $manager->assertValid();

        $this->assertSame(
            ['services', 'blog'],
            $manager->enabledKeys()
        );

        config()->set('features.disabled', [
            'services',
        ]);

        $this->assertSame(
            ['blog'],
            $manager->enabledKeys()
        );
    }

    public function test_unknown_feature_fails_validation(): void
    {
        config()->set('client.vertical', null);
        config()->set('verticals.available', []);
        config()->set('features.available', []);
        config()->set('features.enabled', [
            'not-real',
        ]);
        config()->set('features.disabled', []);

        $manager = new FeatureManager(
            app(),
            new VerticalManager()
        );

        $this->expectException(InvalidArgumentException::class);

        $manager->assertValid();
    }
}