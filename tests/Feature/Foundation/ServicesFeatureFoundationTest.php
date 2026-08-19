<?php

namespace Tests\Feature\Foundation;

use App\Features\Services\ServiceCatalog;
use App\Features\Services\ServicesServiceProvider;
use App\Support\Sections\SectionManager;
use App\Support\SetupValidation\ClientSetupValidator;
use App\Support\SetupValidation\SetupValidationRegistry;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ServicesFeatureFoundationTest extends TestCase
{
    private ?string $temporaryRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->temporaryRoot !== null) {
            File::deleteDirectory($this->temporaryRoot);
        }

        parent::tearDown();
    }

    public function test_services_is_registered_as_an_optional_feature(): void
    {
        $definition = config('features.available.services');

        $this->assertIsArray($definition);
        $this->assertSame(
            ServicesServiceProvider::class,
            $definition['provider'] ?? null,
        );
        $this->assertFalse(
            app(SectionManager::class)->registered('services')
        );
    }

    public function test_enabled_services_provider_registers_section_and_setup_validation_contributor(): void
    {
        $this->bootServicesFeature();

        $this->assertTrue(
            app(SectionManager::class)->registered('services')
        );
        $this->assertContains(
            ServiceCatalog::class,
            array_map(
                static fn (object $contributor): string => $contributor::class,
                app(SetupValidationRegistry::class)->contributors(),
            ),
        );
    }

    public function test_service_catalog_normalizes_grouped_items_facts_and_safe_links(): void
    {
        $this->bootServicesFeature();

        config()->set('app.url', 'https://site.example.test');
        config()->set('features.services', [
            'groups' => [
                'group-a' => [
                    'title' => 'Fixture group',
                ],
            ],
            'items' => [
                'service-a' => [
                    'title' => 'Fixture service',
                    'summary' => 'Fixture summary',
                    'group' => 'group-a',
                    'facts' => [
                        [
                            'label' => 'Fixture label',
                            'value' => 'Fixture value',
                        ],
                    ],
                    'link' => [
                        'label' => 'Fixture link',
                        'url' => 'https://external.example.test/book',
                        'new_tab' => true,
                    ],
                ],
            ],
        ]);

        $catalog = app(ServiceCatalog::class);
        $items = $catalog->items();
        $selection = $catalog->selection('group-a');

        $this->assertSame('group-a', $items['service-a']['group']);
        $this->assertCount(1, $items['service-a']['facts']);
        $this->assertTrue($items['service-a']['link']['external']);
        $this->assertTrue($items['service-a']['link']['new_tab']);
        $this->assertCount(1, $selection);
        $this->assertSame('service-a', $selection[0]['items'][0]['key']);
    }

    public function test_services_section_renders_from_the_central_catalog_contract(): void
    {
        $this->bootServicesFeature();

        config()->set('app.url', 'https://site.example.test');
        config()->set('features.services', [
            'groups' => [],
            'items' => [
                'service-a' => [
                    'title' => 'Fixture service',
                    'facts' => [
                        [
                            'label' => 'Fixture label',
                            'value' => 'Fixture value',
                        ],
                    ],
                    'link' => [
                        'label' => 'Fixture link',
                        'url' => '/destination',
                    ],
                ],
            ],
        ]);
        config()->set('pages', [
            'services-fixture' => [
                'path' => '/services-fixture',
                'sections' => [
                    [
                        'component' => 'services',
                        'layout' => 'three',
                    ],
                ],
            ],
        ]);

        $this->get('/services-fixture')
            ->assertOk()
            ->assertSee('data-section-component="services"', false)
            ->assertSee('<article class="section-card">', false)
            ->assertSee('<strong>', false)
            ->assertSee('href="/destination"', false);
    }

    public function test_services_validation_rejects_unknown_group_references(): void
    {
        $this->bootServicesFeature();

        config()->set('features.services', [
            'groups' => [
                'group-a' => [
                    'title' => 'Fixture group',
                ],
            ],
            'items' => [
                'service-a' => [
                    'title' => 'Fixture service',
                    'group' => 'missing-group',
                ],
            ],
        ]);

        $errors = app(ServiceCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_services_validation_rejects_unsafe_links(): void
    {
        $this->bootServicesFeature();

        config()->set('features.services', [
            'groups' => [],
            'items' => [
                'service-a' => [
                    'title' => 'Fixture service',
                    'link' => [
                        'label' => 'Fixture link',
                        'url' => 'javascript:alert(1)',
                    ],
                ],
            ],
        ]);

        $errors = app(ServiceCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_services_validation_rejects_unknown_section_selection(): void
    {
        $this->bootServicesFeature();

        config()->set('features.services', [
            'groups' => [],
            'items' => [
                'service-a' => [
                    'title' => 'Fixture service',
                ],
            ],
        ]);
        config()->set('pages', [
            'services-fixture' => [
                'path' => '/services-fixture',
                'sections' => [
                    [
                        'component' => 'services',
                        'props' => [
                            'items' => [
                                'missing-service',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $errors = app(ServiceCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_setup_validate_includes_enabled_feature_contributors(): void
    {
        $this->bootServicesFeature();

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-services-'.bin2hex(random_bytes(6));

        $clientDirectory = $this->temporaryRoot.'/clients/services-test-client';

        File::ensureDirectoryExists($clientDirectory.'/config/pages');
        File::ensureDirectoryExists($clientDirectory.'/resources/views');
        File::ensureDirectoryExists($clientDirectory.'/resources/images/raw');

        File::put(
            $clientDirectory.'/config/client.php',
            "<?php\n\nreturn [\n    'key' => 'services-test-client',\n];\n",
        );
        File::put(
            $clientDirectory.'/config/features.php',
            "<?php\n\nreturn [\n    'enabled' => ['services'],\n    'disabled' => [],\n];\n",
        );
        File::put(
            $clientDirectory.'/config/site.php',
            "<?php\n\nreturn [\n    'name' => 'Fixture Site',\n];\n",
        );
        File::put(
            $clientDirectory.'/.env.example',
            "APP_URL=\nDB_DATABASE=\nDB_USERNAME=\nDB_PASSWORD=\n",
        );
        File::put(
            $clientDirectory.'/.env',
            implode(PHP_EOL, [
                'APP_URL=https://site.example.test',
                'DB_DATABASE=services_database',
                'DB_USERNAME=services_user',
                'DB_PASSWORD=',
                '',
            ]),
        );

        config()->set('client.key', 'services-test-client');
        config()->set('client.timezone', 'America/Chicago');
        config()->set('client.vertical', null);
        config()->set('features.enabled', ['services']);
        config()->set('features.disabled', []);
        config()->set('features.services', [
            'groups' => [],
            'items' => [],
        ]);
        config()->set('pages', []);
        config()->set('site.seo.indexing_enabled', false);
        config()->set('site.seo.sitemap_enabled', true);
        config()->set('site.seo.redirects', []);
        config()->set('seo_migration.enabled', false);

        $result = app(ClientSetupValidator::class)->validate(
            $this->temporaryRoot,
        );

        $this->assertFalse($result->valid());
        $this->assertNotEmpty($result->errors);
    }

    private function bootServicesFeature(): void
    {
        $provider = new ServicesServiceProvider(app());

        $provider->register();
        $provider->boot(
            app(SetupValidationRegistry::class),
        );
    }
}