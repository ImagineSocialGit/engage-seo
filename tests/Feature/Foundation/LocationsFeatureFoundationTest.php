<?php

namespace Tests\Feature\Foundation;

use App\Features\Locations\GeographicTargetCatalog;
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

    public function test_enabled_locations_provider_registers_catalog_and_geographic_target_sections_and_validation_contributors(): void
    {
        $this->bootLocationsFeature();

        $this->assertTrue(
            app(SectionManager::class)->registered('locations')
        );
        $this->assertTrue(
            app(SectionManager::class)->registered('geographic-targets')
        );

        $contributors = array_map(
            static fn (object $contributor): string => $contributor::class,
            app(SetupValidationRegistry::class)->contributors(),
        );

        $this->assertContains(LocationCatalog::class, $contributors);
        $this->assertContains(GeographicTargetCatalog::class, $contributors);
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

    public function test_geographic_targets_normalize_hierarchy_page_references_and_service_semantics(): void
    {
        $this->bootLocationsFeature();

        config()->set('app.url', 'https://site.example.test');
        config()->set('pages', [
            'state-page' => [
                'path' => '/state-a',
                'meta' => [
                    'indexable' => true,
                ],
                'sections' => [],
            ],
            'city-page' => [
                'path' => '/city-a',
                'meta' => [
                    'indexable' => false,
                ],
                'sections' => [],
            ],
            'related-page' => [
                'path' => '/city-b',
                'sections' => [],
            ],
        ]);
        config()->set('features.locations', [
            'groups' => [],
            'items' => [],
            'targets' => [
                'state-a' => [
                    'type' => 'state',
                    'title' => 'Fixture State',
                    'summary' => 'Fixture state summary.',
                    'page' => 'state-page',
                    'service_area' => true,
                    'physical_location' => false,
                    'geography' => [
                        'country_code' => 'us',
                        'state_code' => 'aa',
                    ],
                ],
                'city-a' => [
                    'type' => 'city',
                    'title' => 'Fixture City A',
                    'parent' => 'state-a',
                    'page' => 'city-page',
                    'service_area' => true,
                    'physical_location' => false,
                    'geography' => [
                        'country_code' => 'US',
                        'state_code' => 'AA',
                        'county' => 'Fixture County',
                        'metro' => 'Fixture Metro',
                    ],
                    'related' => [
                        'city-b',
                    ],
                ],
                'city-b' => [
                    'type' => 'market',
                    'title' => 'Fixture Market B',
                    'parent' => 'state-a',
                    'page' => 'related-page',
                    'service_area' => true,
                    'physical_location' => false,
                ],
            ],
        ]);

        $catalog = app(GeographicTargetCatalog::class);
        $targets = $catalog->targets();

        $this->assertSame('/state-a', $targets['state-a']['page']['path']);
        $this->assertTrue($targets['state-a']['page']['indexable']);
        $this->assertFalse($targets['city-a']['page']['indexable']);
        $this->assertSame('US', $targets['city-a']['geography']['country_code']);
        $this->assertSame('AA', $targets['city-a']['geography']['state_code']);
        $this->assertTrue($targets['city-a']['service_area']);
        $this->assertFalse($targets['city-a']['physical_location']);
        $this->assertSame(
            ['city-a', 'city-b'],
            array_column($catalog->children('state-a'), 'key'),
        );
        $this->assertSame(
            ['state-a'],
            array_column($catalog->ancestors('city-a'), 'key'),
        );
        $this->assertSame(
            ['city-b'],
            array_column($catalog->relatedTargets('city-a'), 'key'),
        );
        $this->assertSame('/city-a', $catalog->pagePath('city-a'));
    }

    public function test_geographic_targets_section_renders_root_and_child_page_links(): void
    {
        $this->bootLocationsFeature();

        config()->set('app.url', 'https://site.example.test');
        config()->set('features.locations', [
            'groups' => [],
            'items' => [],
            'targets' => [
                'state-a' => [
                    'type' => 'state',
                    'title' => 'Fixture State',
                    'summary' => 'Fixture state summary.',
                    'page' => 'state-page',
                    'service_area' => true,
                    'physical_location' => false,
                ],
                'city-a' => [
                    'type' => 'city',
                    'title' => 'Fixture City',
                    'parent' => 'state-a',
                    'page' => 'city-page',
                    'service_area' => true,
                    'physical_location' => false,
                ],
            ],
        ]);
        config()->set('pages', [
            'state-page' => [
                'path' => '/state-a',
                'sections' => [],
            ],
            'city-page' => [
                'path' => '/city-a',
                'sections' => [],
            ],
            'locations-fixture' => [
                'path' => '/locations-fixture',
                'sections' => [
                    [
                        'component' => 'geographic-targets',
                        'layout' => 'two',
                        'props' => [
                            'title' => 'Fixture geographic targets',
                        ],
                    ],
                ],
            ],
        ]);

        $this->get('/locations-fixture')
            ->assertOk()
            ->assertSee('data-section-component="geographic-targets"', false)
            ->assertSee('data-geographic-target="state-a"', false)
            ->assertSee('href="/state-a"', false)
            ->assertSee('href="/city-a"', false);
    }

    public function test_target_only_configuration_does_not_require_legacy_location_items(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [],
            'items' => [],
            'targets' => [
                'region-a' => [
                    'type' => 'region',
                    'title' => 'Fixture Region',
                    'service_area' => true,
                    'physical_location' => false,
                ],
            ],
        ]);

        $this->assertSame(
            [],
            app(LocationCatalog::class)->validationErrors(),
        );
    }

    public function test_geographic_target_validation_rejects_unknown_page_references(): void
    {
        $this->bootLocationsFeature();

        config()->set('app.url', 'https://site.example.test');
        config()->set('pages', []);
        config()->set('features.locations', [
            'groups' => [],
            'items' => [],
            'targets' => [
                'region-a' => [
                    'type' => 'region',
                    'title' => 'Fixture Region',
                    'page' => 'missing-page',
                    'service_area' => true,
                    'physical_location' => false,
                ],
            ],
        ]);

        $errors = app(GeographicTargetCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_geographic_target_validation_rejects_parent_cycles(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [],
            'items' => [],
            'targets' => [
                'region-a' => [
                    'type' => 'region',
                    'title' => 'Fixture Region A',
                    'parent' => 'region-b',
                    'service_area' => true,
                    'physical_location' => false,
                ],
                'region-b' => [
                    'type' => 'region',
                    'title' => 'Fixture Region B',
                    'parent' => 'region-a',
                    'service_area' => true,
                    'physical_location' => false,
                ],
            ],
        ]);

        $errors = app(GeographicTargetCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_geographic_target_validation_rejects_address_for_nonphysical_target(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [],
            'items' => [],
            'targets' => [
                'market-a' => [
                    'type' => 'market',
                    'title' => 'Fixture Market',
                    'service_area' => true,
                    'physical_location' => false,
                    'address' => [
                        '123 Fixture Street',
                    ],
                ],
            ],
        ]);

        $errors = app(GeographicTargetCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_geographic_target_validation_rejects_unknown_section_selection(): void
    {
        $this->bootLocationsFeature();

        config()->set('features.locations', [
            'groups' => [],
            'items' => [],
            'targets' => [
                'region-a' => [
                    'type' => 'region',
                    'title' => 'Fixture Region',
                    'service_area' => true,
                    'physical_location' => false,
                ],
            ],
        ]);
        config()->set('pages', [
            'locations-fixture' => [
                'path' => '/locations-fixture',
                'sections' => [
                    [
                        'component' => 'geographic-targets',
                        'props' => [
                            'targets' => [
                                'missing-target',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $errors = app(GeographicTargetCatalog::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_setup_validate_includes_enabled_locations_contributor(): void
    {
        $this->bootLocationsFeature();

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-locations-'.bin2hex(random_bytes(6));

        $clientDirectory = $this->temporaryRoot.'/client/locations-test-client';

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
            'targets' => [],
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