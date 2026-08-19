<?php

namespace Tests\Feature\Foundation;

use App\Support\Site\SitePresentationResolver;
use Tests\TestCase;

class BusinessSiteShellFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('app.url', 'https://site.example.test');
        config()->set('site.name', 'Fixture Site');
    }

    public function test_business_shell_normalizes_contact_utility_footer_and_link_behavior(): void
    {
        config()->set('site.business', [
            'phone' => [
                'label' => 'Fixture phone',
                'value' => '123 456 7890',
                'url' => 'tel:+11234567890',
            ],
            'email' => [
                'label' => 'Fixture email',
                'value' => 'fixture@example.test',
                'url' => 'mailto:fixture@example.test',
            ],
            'address' => [
                'lines' => ['Fixture street', 'Fixture city'],
                'url' => 'https://maps.example.test/location',
                'new_tab' => true,
            ],
            'social_links' => [
                [
                    'label' => 'Fixture social',
                    'url' => 'https://social.example.test/fixture',
                    'new_tab' => true,
                ],
            ],
        ]);

        config()->set('site.shell.utility_bar', [
            'enabled' => true,
            'theme' => 'inverse',
            'items' => [
                ['text' => 'Fixture disclosure'],
                [
                    'label' => 'Fixture utility link',
                    'url' => '/fixture',
                ],
            ],
        ]);

        config()->set('site.shell.header.theme', 'inverse');
        config()->set('site.shell.footer', [
            'enabled' => true,
            'theme' => 'inverse',
            'intro' => 'Fixture footer intro',
            'groups' => [
                [
                    'label' => 'Fixture group',
                    'items' => [
                        [
                            'label' => 'Fixture current page',
                            'url' => '/fixture',
                        ],
                    ],
                ],
            ],
            'cta' => [
                'title' => 'Fixture CTA',
                'description' => 'Fixture CTA description',
                'actions' => [
                    [
                        'label' => 'Fixture external action',
                        'url' => 'https://external.example.test/action',
                        'new_tab' => true,
                    ],
                ],
            ],
            'legal' => [
                'lines' => ['Fixture legal line'],
                'links' => [
                    [
                        'label' => 'Fixture legal link',
                        'url' => '/legal',
                    ],
                ],
            ],
        ]);

        $site = app(SitePresentationResolver::class)->resolve('/fixture');

        $this->assertSame('tel:+11234567890', $site['business']['phone']['url']);
        $this->assertSame('mailto:fixture@example.test', $site['business']['email']['url']);
        $this->assertTrue($site['business']['address']['external']);
        $this->assertTrue($site['business']['social_links'][0]['external']);
        $this->assertTrue($site['business']['social_links'][0]['new_tab']);
        $this->assertSame('text', $site['shell']['utility_bar']['items'][0]['type']);
        $this->assertTrue($site['shell']['utility_bar']['items'][1]['active']);
        $this->assertSame('inverse', $site['shell']['header']['theme']);
        $this->assertSame('inverse', $site['shell']['footer']['theme']);
        $this->assertTrue($site['shell']['footer']['groups'][0]['items'][0]['active']);
        $this->assertTrue($site['shell']['footer']['cta']['actions'][0]['external']);
        $this->assertTrue($site['shell']['footer']['cta']['actions'][0]['new_tab']);
    }

    public function test_business_shell_renders_semantic_utility_contact_groups_cta_and_legal_regions(): void
    {
        config()->set('site.business.phone', [
            'label' => 'Fixture phone',
            'value' => '123 456 7890',
            'url' => 'tel:+11234567890',
        ]);
        config()->set('site.business.social_links', [
            [
                'label' => 'Fixture social',
                'url' => 'https://social.example.test/fixture',
                'new_tab' => true,
            ],
        ]);
        config()->set('site.shell.utility_bar', [
            'enabled' => true,
            'theme' => 'inverse',
            'items' => [
                ['text' => 'Fixture disclosure'],
            ],
        ]);
        config()->set('site.shell.footer', [
            'enabled' => true,
            'theme' => 'inverse',
            'intro' => null,
            'groups' => [
                [
                    'label' => 'Fixture group',
                    'items' => [
                        [
                            'label' => 'Fixture destination',
                            'url' => '/destination',
                        ],
                    ],
                ],
            ],
            'cta' => [
                'title' => 'Fixture CTA',
                'actions' => [
                    [
                        'label' => 'Fixture action',
                        'url' => '/destination',
                    ],
                ],
            ],
            'legal' => [
                'lines' => ['Fixture legal line'],
                'links' => [],
            ],
        ]);
        config()->set('pages', [
            'fixture' => [
                'path' => '/fixture',
                'sections' => [],
            ],
        ]);

        $response = $this->get('/fixture');

        $response
            ->assertOk()
            ->assertSee('class="site-utility-bar shell-region"', false)
            ->assertSee('<address class="site-footer__contact">', false)
            ->assertSee('aria-label="Social links"', false)
            ->assertSee('class="site-footer__groups"', false)
            ->assertSee('class="site-footer__cta"', false)
            ->assertSee('class="site-footer__legal"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_business_shell_rejects_malformed_contact_and_unsafe_social_contracts(): void
    {
        config()->set('site.business.phone', [
            'label' => 'Fixture phone',
            'value' => '123 456 7890',
            'url' => 'https://example.test/not-a-phone',
        ]);
        config()->set('site.business.social_links', [
            [
                'label' => 'Fixture social',
                'url' => 'javascript:alert(1)',
            ],
        ]);

        $errors = app(SitePresentationResolver::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_footer_groups_and_cta_require_meaningful_link_content(): void
    {
        config()->set('site.shell.footer.groups', [
            [
                'label' => 'Fixture group',
                'items' => [],
            ],
        ]);

        $errors = app(SitePresentationResolver::class)->validationErrors();

        $this->assertNotEmpty($errors);
    }
}