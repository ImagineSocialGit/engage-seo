<?php

namespace Tests\Feature\Foundation;

use App\Support\Site\SitePresentationResolver;
use Tests\TestCase;

class SiteShellFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_site_presentation_normalizes_navigation_active_state_and_theme_contract(): void
    {
        config()->set('site.name', null);
        config()->set('client.name', 'Presentation Test Site');

        config()->set('site.shell.navigation.items', [
            [
                'label' => 'First',
                'url' => '/first',
            ],
            [
                'label' => 'Group',
                'children' => [
                    [
                        'label' => 'Nested',
                        'url' => '/nested',
                    ],
                ],
            ],
        ]);

        $site = app(SitePresentationResolver::class)->resolve('/nested');

        $this->assertSame(
            'Presentation Test Site',
            $site['name']
        );
        $this->assertFalse(
            $site['shell']['navigation']['items'][0]['active']
        );
        $this->assertTrue(
            $site['shell']['navigation']['items'][1]['active']
        );
        $this->assertTrue(
            $site['shell']['navigation']['items'][1]['children'][0]['active']
        );
        $this->assertSame(
            config('site.theme.colors.primary'),
            $site['theme']['css_variables']['--site-color-primary']
        );
        $this->assertSame(
            config('site.theme.layout.content_max_width'),
            $site['theme']['css_variables']['--site-content-max-width']
        );
    }

    public function test_invalid_navigation_contract_is_reported_without_rendering_a_page(): void
    {
        config()->set('site.shell.navigation.items', [
            [
                'label' => 'Missing destination',
            ],
        ]);

        $errors = app(SitePresentationResolver::class)
            ->validationErrors();

        $this->assertCount(1, $errors);
    }

    public function test_shell_enablement_controls_semantic_header_and_footer_regions(): void
    {
        config()->set('app.url', 'https://site.example.test');
        config()->set('site.shell.header.enabled', false);
        config()->set('site.shell.footer.enabled', true);
        config()->set('pages', [
            'shell' => [
                'path' => '/shell',
                'sections' => [],
            ],
        ]);

        $response = $this->get('/shell');

        $response
            ->assertOk()
            ->assertDontSee('<header', false)
            ->assertSee('<footer', false);
    }
}