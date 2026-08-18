<?php

namespace Tests\Feature\Foundation;

use App\Contracts\Seo\SitemapContributor;
use App\Contracts\Seo\StructuredDataContributor;
use App\Support\Seo\RedirectRepository;
use App\Support\Seo\SeoExtensionRegistry;
use App\Support\Seo\StructuredDataResolver;
use Tests\TestCase;

class SeoInfrastructureFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('client.key', 'seo-test-client');
        config()->set('client.name', 'SEO Test Client');
        config()->set('app.url', 'https://site.example.test');
        config()->set('site.name', 'SEO Test Site');
        config()->set('site.seo.indexing_enabled', true);
        config()->set('site.seo.sitemap_enabled', true);
        config()->set('site.seo.redirects', []);
        config()->set('pages', []);
    }

    public function test_non_production_environment_blocks_page_and_robot_indexing(): void
    {
        config()->set('app.env', 'staging');
        config()->set('pages', [
            'example' => [
                'path' => '/example',
                'meta' => [
                    'indexable' => true,
                ],
                'sections' => [],
            ],
        ]);

        $this->get('/example')
            ->assertOk()
            ->assertSee(
                '<meta name="robots" content="noindex,nofollow">',
                false,
            );

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'text/plain; charset=UTF-8',
            )
            ->assertSee('Disallow: /');
    }

    public function test_production_indexing_requires_explicit_launch_switch(): void
    {
        config()->set('app.env', 'production');
        config()->set('site.seo.indexing_enabled', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /');

        $this->get('/sitemap.xml')
            ->assertNotFound();
    }

    public function test_sitemap_contains_only_effectively_indexable_configured_pages(): void
    {
        config()->set('app.env', 'production');
        config()->set('pages', [
            'included' => [
                'path' => '/included',
                'meta' => [
                    'indexable' => true,
                ],
                'sections' => [],
            ],
            'excluded' => [
                'path' => '/excluded',
                'meta' => [
                    'indexable' => false,
                ],
                'sections' => [],
            ],
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/xml; charset=UTF-8',
            )
            ->assertSee(
                'https://site.example.test/included',
                false,
            )
            ->assertDontSee(
                'https://site.example.test/excluded',
                false,
            );

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Allow: /')
            ->assertSee(
                'Sitemap: https://site.example.test/sitemap.xml',
                false,
            );
    }

    public function test_configured_redirect_is_resolved_before_static_page_rendering(): void
    {
        config()->set('site.seo.redirects', [
            [
                'from' => '/old-path',
                'to' => '/new-path',
                'status' => 301,
            ],
        ]);

        $this->get('/old-path')
            ->assertRedirect('/new-path')
            ->assertStatus(301);
    }

    public function test_redirect_chains_are_setup_validation_errors(): void
    {
        config()->set('site.seo.redirects', [
            [
                'from' => '/first',
                'to' => '/second',
            ],
            [
                'from' => '/second',
                'to' => '/final',
            ],
        ]);

        $errors = app(RedirectRepository::class)
            ->validationErrors();

        $this->assertCount(1, $errors);
    }

    public function test_sitemap_and_structured_data_support_feature_contributors(): void
    {
        config()->set('app.env', 'production');
        config()->set('pages', [
            'example' => [
                'path' => '/example',
                'meta' => [
                    'structured_data' => [
                        [
                            '@context' => 'https://schema.org',
                            '@type' => 'WebPage',
                        ],
                    ],
                ],
                'sections' => [],
            ],
        ]);

        $extensions = app(SeoExtensionRegistry::class);

        $extensions->registerSitemapContributor(
            FoundationSitemapContributor::class
        );
        $extensions->registerStructuredDataContributor(
            FoundationStructuredDataContributor::class
        );

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(
                'https://site.example.test/feature-entry',
                false,
            );

        $page = app(\App\Support\Pages\PageRepository::class)
            ->resolvePath('/example');
        $site = app(\App\Support\Site\SitePresentationResolver::class)
            ->resolve('/example');

        $nodes = app(StructuredDataResolver::class)
            ->resolve($page, $site);

        $this->assertCount(2, $nodes);
        $this->assertSame(
            'WebPage',
            $nodes[0]['@type']
        );
        $this->assertSame(
            'Thing',
            $nodes[1]['@type']
        );
    }
}

final class FoundationSitemapContributor implements SitemapContributor
{
    public function sitemapEntries(): array
    {
        return [
            [
                'loc' => 'https://site.example.test/feature-entry',
            ],
        ];
    }
}

final class FoundationStructuredDataContributor implements StructuredDataContributor
{
    public function structuredData(array $page, array $site): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Thing',
            ],
        ];
    }
}