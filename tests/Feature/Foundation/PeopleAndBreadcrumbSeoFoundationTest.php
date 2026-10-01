<?php

namespace Tests\Feature\Foundation;

use App\Features\Blog\BlogAuthorResolver;
use App\Support\Pages\PageRepository;
use App\Support\Seo\StructuredDataResolver;
use App\Support\Site\SitePresentationResolver;
use Tests\TestCase;

class PeopleAndBreadcrumbSeoFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('app.url', 'https://site.example.test');
        config()->set('site.name', 'Fixture Site');
        config()->set('site.people', [
            'fixture-author' => [
                'name' => 'Fixture Author',
                'url' => '/about-fixture-author',
                'job_title' => 'Fixture Specialist',
                'description' => 'Fixture public biography.',
                'same_as' => [
                    'https://profiles.example.test/fixture-author',
                ],
            ],
        ]);
        config()->set('features.blog.author_profiles', [
            'Fixture Author' => 'fixture-author',
        ]);
    }

    public function test_site_people_are_normalized_and_available_to_blog_authors(): void
    {
        $site = app(SitePresentationResolver::class)->resolve('/');

        $this->assertSame(
            'Fixture Author',
            $site['people']['fixture-author']['name'],
        );
        $this->assertSame(
            '/about-fixture-author',
            $site['people']['fixture-author']['url'],
        );

        $author = app(BlogAuthorResolver::class)->resolve('Fixture Author');

        $this->assertSame('Fixture Author', $author['name']);
        $this->assertSame(
            'fixture-author',
            $author['profile']['key'],
        );
    }

    public function test_page_breadcrumbs_render_and_emit_structured_data(): void
    {
        config()->set('pages', [
            'fixture' => [
                'path' => '/fixture',
                'person' => 'fixture-author',
                'breadcrumbs' => [
                    [
                        'label' => 'Home',
                        'url' => '/',
                    ],
                    [
                        'label' => 'Fixture',
                        'url' => '/fixture',
                    ],
                ],
                'sections' => [
                    [
                        'component' => 'faq',
                        'props' => [
                            'items' => [
                                [
                                    'question' => 'Fixture question?',
                                    'answer' => 'Fixture answer.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->get('/fixture');

        $response
            ->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('href="/"', false)
            ->assertSee('Fixture');

        $page = app(PageRepository::class)->resolvePath('/fixture');
        $site = app(SitePresentationResolver::class)->resolve('/fixture');
        $nodes = app(StructuredDataResolver::class)->resolve($page, $site);

        $types = array_column($nodes, '@type');

        $this->assertContains('Organization', $types);
        $this->assertContains('Person', $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertContains('FAQPage', $types);

        $person = collect($nodes)->firstWhere('@type', 'Person');
        $this->assertSame(
            'https://site.example.test/about-fixture-author',
            $person['url'],
        );
    }

    public function test_breadcrumbs_must_end_at_current_page(): void
    {
        config()->set('pages', [
            'fixture' => [
                'path' => '/fixture',
                'breadcrumbs' => [
                    [
                        'label' => 'Wrong',
                        'url' => '/other',
                    ],
                ],
                'sections' => [],
            ],
        ]);

        $errors = app(PageRepository::class)->validationErrors();

        $this->assertCount(1, $errors);
    }

    public function test_unknown_blog_author_profile_is_setup_validation_error(): void
    {
        config()->set('site.people', []);
        config()->set('features.blog.author_profiles', [
            'Fixture Author' => 'missing-person',
        ]);

        $errors = app(\App\Features\Blog\BlogSetupValidator::class)
            ->validationErrors();

        $this->assertContains(
            'Blog author profile [Fixture Author] references unknown site person [missing-person].',
            $errors,
        );
    }
}