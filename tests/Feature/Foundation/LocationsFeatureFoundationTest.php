<?php

namespace Tests\Feature\Foundation;

use App\Features\Locations\LocationCatalog;
use App\Features\Locations\LocationsServiceProvider;
use App\Support\Sections\SectionManager;
use App\Support\SetupValidation\ClientSetupValidator;
use App\Support\SetupValidation\SetupValidationRegistry;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocationsFeatureFoundationTest extends TestCase
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

    public function test_locations_is_registered_as_an_optional_feature(): void
    {
        $definition = config('features.available.locations');

        $this->assertIsArray($definition);
        $this->assertSame(
            LocationsServiceProvider::class,
            $definition['provider'] ?? null,
        );
        $this->assertFalse(
            app(SectionManager::class)->registered('locations')
        );
    }

    public function test_enabled_locations_provider_registers_section_and_setup_validation_contributor(): void
    {
        $this->bootLocationsFeature();

        $this->assertTrue(
            app(SectionManager::class)->registered('locations')
        );
        $this->assertContains(
            LocationCatalog::class,
            array_map(
                static fn (object $contributor): string => $contributor::class,
                app(SetupValidationRegistry::class)->contributors(),
            ),
        );
    }

    public function test_location_catalog_normalizes_grouped_items_address_facts_and_safe_links(): void
    {
        $this->bootLocationsFeature();

        config()->set('app.url', 'https://site.example.test');
        config()->set('features.locations', [
            'groups' => [
                'region-a' => [
                    'title' => 'Fixture region',
                ],
            ],
            'items' => [
                'location-a' => [
                    'title' => 'Fixture location',
                    'summary' => 'Fixture summary',
                    'group' => 'region-a',
                    'address' => [
                        '123 Fixture Street',
                        'Fixture City, ST 12345',
                    ],
                    'facts' => [
                        [
                            'label' => 'Fixture label',
                            'value' => 'Fixture value',
                        ],
                    ],
                    'links' => [
                        [
                            'label' => 'Fixture link',
                            'url' => 'https://external.example.test/location',
                            'new_tab' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $catalog = app(LocationCatalog::class);
        $items = $catalog->items();
        $selection = $catalog->selection('region-a');

        $this->assertSame('region-a', $items['location-a']['group']);
        $this->assertCount(2, $items['location-a']['address']);
        $this->assertCount(1, $items['location-a']['facts']);
        $this->assertTrue($items['location-a']['links'][0]['external']);
        $this->assertTrue($items['location-a']['links'][0]['new_tab']);
        $this->assertCount(1, $selection);
        $this->assertSame('location-a', $selection[0]['items'][0]['key']);
    }

    public function test_locations_section_renders_from_the_central_catalog_contract(): void
    {
        $this->bootLocationsFeature();

        config()->set('app.url', 'https://site.example.test');
        config()->set('features.locations', [
            'groups' => [],
            'items' => [
                'location-a' => [
                    'title' => 'Fixture location',
                    'address' => [
                        '123 Fixture Street',
                    ],
                    'links' => [
                        [
                            'label' => 'Fixture link',
                            'url' => '/destination',
                        ],
                    ],
                ],
            ],
        ]);
        config()->set('pages', [
            'locations-fixture' => [
                'path' => '/locations-fixture',
                'sections' => [
                    [
                        'component' => 'locations',
                        'layout' => 'three',
                    ],
                ],
            ],
        ]);

        $this->get('/locations-fixture')
            ->assertOk()
            ->assertSee('data-section-component="locations"', false)
            ->assertSee('<address', false)
            ->assertSee('href="/destination"', false);
    }

    public function test_locations_selection_can_render_an_explicit_ordered_subset(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [],
            'items' => [
                'location-a' => [
                    'title' => 'Fixture A',
                ],
                'location-b' => [
                    'title' => 'Fixture B',
                ],
            ],
        ]);

        $selection = app(LocationCatalog::class)->selection(
            null,
            ['location-b', 'location-a'],
        );

        $this->assertSame(
            ['location-b', 'location-a'],
            array_column($selection[0]['items'], 'key'),
        );
    }

    public function test_locations_validation_rejects_unknown_group_references(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [
                'region-a' => [
                    'title' => 'Fixture region',
                ],
            ],
            'items' => [
                'location-a' => [
                    'title' => 'Fixture location',
                    'group' => 'missing-region',
                ],
            ],
        ]);

        $errors = app(LocationCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_locations_validation_rejects_malformed_address_and_unsafe_links(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [],
            'items' => [
                'location-a' => [
                    'title' => 'Fixture location',
                    'address' => 'not-a-list',
                    'links' => [
                        [
                            'label' => 'Fixture link',
                            'url' => 'javascript:alert(1)',
                        ],
                    ],
                ],
            ],
        ]);

        $errors = app(LocationCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_locations_validation_rejects_unknown_section_selection(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [],
            'items' => [
                'location-a' => [
                    'title' => 'Fixture location',
                ],
            ],
        ]);
        config()->set('pages', [
            'locations-fixture' => [
                'path' => '/locations-fixture',
                'sections' => [
                    [
                        'component' => 'locations',
                        'props' => [
                            'items' => [
                                'missing-location',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $errors = app(LocationCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_setup_validate_includes_enabled_locations_contributor(): void
    {
        $this->bootLocationsFeature();

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-locations-'.bin2hex(random_bytes(6));

        $clientDirectory = $this->temporaryRoot.'/clients/locations-test-client';

        File::ensureDirectoryExists($clientDirectory.'/config/pages');
        File::ensureDirectoryExists($clientDirectory.'/resources/views');
        File::ensureDirectoryExists($clientDirectory.'/resources/images/raw');

        File::put(
            $clientDirectory.'/config/client.php',
            "<?php\n\nreturn [\n    'key' => 'locations-test-client',\n];\n",
        );
        File::put(
            $clientDirectory.'/config/features.php',
            "<?php\n\nreturn [\n    'enabled' => ['locations'],\n    'disabled' => [],\n];\n",
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
                'DB_DATABASE=locations_database',
                'DB_USERNAME=locations_user',
                'DB_PASSWORD=',
                '',
            ]),
        );

        config()->set('client.key', 'locations-test-client');
        config()->set('client.timezone', 'America/Chicago');
        config()->set('client.vertical', null);
        config()->set('features.enabled', ['locations']);
        config()->set('features.disabled', []);
        config()->set('features.locations', [
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

    private function bootLocationsFeature(): void
    {
        $provider = new LocationsServiceProvider(app());

        $provider->register();
        $provider->boot(
            app(SetupValidationRegistry::class),
        );
    }
}