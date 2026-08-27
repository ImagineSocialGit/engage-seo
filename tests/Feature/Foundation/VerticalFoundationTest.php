<?php

namespace Tests\Feature\Foundation;

use App\Support\Features\FeatureManager;
use App\Support\Verticals\VerticalManager;
use InvalidArgumentException;
use Tests\TestCase;

class VerticalFoundationTest extends TestCase
{
    protected function tearDown(): void
    {
        config()->set('client.vertical', null);
        config()->set('features.enabled', []);
        config()->set('features.disabled', []);

        parent::tearDown();
    }

    public function test_platform_registers_mortgage_and_pets_verticals(): void
    {
        $available = app(VerticalManager::class)->available();

        $this->assertArrayHasKey('mortgage', $available);
        $this->assertArrayHasKey('pets', $available);
    }

    public function test_mortgage_vertical_composes_existing_reusable_features(): void
    {
        config()->set('client.vertical', 'mortgage');

        $features = app(FeatureManager::class);

        $features->assertValid();

        $this->assertSame(
            ['services', 'locations', 'blog'],
            $features->enabledKeys(),
        );
    }

    public function test_pets_vertical_composes_existing_reusable_features(): void
    {
        config()->set('client.vertical', 'pets');

        $features = app(FeatureManager::class);

        $features->assertValid();

        $this->assertSame(
            ['services', 'locations', 'blog'],
            $features->enabledKeys(),
        );
    }

    public function test_client_disable_list_remains_final_authority_over_vertical_defaults(): void
    {
        config()->set('client.vertical', 'mortgage');
        config()->set('features.disabled', [
            'blog',
        ]);

        $this->assertSame(
            ['services', 'locations'],
            app(FeatureManager::class)->enabledKeys(),
        );
    }

    public function test_vertical_registry_rejects_unsupported_definition_keys(): void
    {
        config()->set('verticals.available', [
            'fixture' => [
                'name' => 'Fixture',
                'default_features' => [],
                'client_copy' => 'not part of the Vertical contract',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(VerticalManager::class)->assertValid();
    }

    public function test_feature_validation_rejects_unknown_feature_in_any_registered_vertical(): void
    {
        config()->set('verticals.available', [
            'fixture' => [
                'name' => 'Fixture',
                'default_features' => [
                    'not-a-feature',
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(FeatureManager::class)->assertValid();
    }
}