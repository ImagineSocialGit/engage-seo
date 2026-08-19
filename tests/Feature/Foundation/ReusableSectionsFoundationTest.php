<?php

namespace Tests\Feature\Foundation;

use App\Support\Pages\PageRepository;
use App\Support\Sections\SectionManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Tests\TestCase;

class ReusableSectionsFoundationTest extends TestCase
{
    private ?string $temporaryClientViewPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->temporaryClientViewPath !== null) {
            File::deleteDirectory($this->temporaryClientViewPath);
        }

        parent::tearDown();
    }

    public function test_platform_registers_the_reusable_section_library(): void
    {
        $manager = app(SectionManager::class);

        $this->assertEqualsCanonicalizing([
            'content',
            'hero',
            'content-split',
            'card-grid',
            'steps',
            'cta',
            'media-embed',
            'stats',
            'testimonials',
            'faq',
        ], array_keys($manager->available()));

        $this->assertSame([], $manager->validationErrors());

        foreach (array_keys($manager->available()) as $component) {
            $this->assertTrue(
                View::exists($manager->viewFor($component))
            );
        }
    }

    public function test_reusable_sections_compose_through_the_normalized_page_contract(): void
    {
        config()->set('app.url', 'https://site.example.test');
        config()->set('pages', [
            'sections' => [
                'path' => '/sections',
                'sections' => [
                    [
                        'component' => 'hero',
                        'theme' => 'inverse',
                        'layout' => 'centered',
                        'props' => [
                            'title' => 'Fixture heading',
                            'actions' => [
                                [
                                    'label' => 'Fixture action',
                                    'url' => '/destination',
                                ],
                            ],
                        ],
                    ],
                    [
                        'component' => 'content',
                        'layout' => 'narrow',
                        'props' => [
                            'title' => 'Fixture heading',
                            'content' => [
                                'Fixture paragraph',
                            ],
                        ],
                    ],
                    [
                        'component' => 'content-split',
                        'layout' => 'media-right',
                        'props' => [
                            'title' => 'Fixture heading',
                        ],
                    ],
                    [
                        'component' => 'card-grid',
                        'layout' => 'three',
                        'props' => [
                            'items' => [
                                [
                                    'title' => 'Fixture card',
                                    'description' => 'Fixture description',
                                ],
                            ],
                        ],
                    ],
                    [
                        'component' => 'steps',
                        'layout' => 'three',
                        'props' => [
                            'items' => [
                                [
                                    'title' => 'Fixture step',
                                ],
                            ],
                        ],
                    ],
                    [
                        'component' => 'cta',
                        'theme' => 'accent',
                        'layout' => 'split',
                        'props' => [
                            'title' => 'Fixture heading',
                        ],
                    ],
                    [
                        'component' => 'media-embed',
                        'layout' => 'wide',
                        'props' => [
                            'embed' => [
                                'src' => 'https://video.example.test/embed/fixture',
                                'title' => 'Fixture video',
                            ],
                        ],
                    ],
                    [
                        'component' => 'stats',
                        'layout' => 'three',
                        'props' => [
                            'items' => [
                                [
                                    'value' => '1',
                                    'label' => 'Fixture stat',
                                ],
                            ],
                        ],
                    ],
                    [
                        'component' => 'testimonials',
                        'layout' => 'three',
                        'props' => [
                            'items' => [
                                [
                                    'quote' => 'Fixture quote',
                                    'rating' => 5,
                                ],
                            ],
                        ],
                    ],
                    [
                        'component' => 'faq',
                        'layout' => 'narrow',
                        'props' => [
                            'items' => [
                                [
                                    'question' => 'Fixture question',
                                    'answer' => 'Fixture answer',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->get('/sections');

        $response
            ->assertOk()
            ->assertSee('data-section-component="hero"', false)
            ->assertSee('data-section-component="content-split"', false)
            ->assertSee('data-section-component="card-grid"', false)
            ->assertSee('data-section-component="steps"', false)
            ->assertSee('data-section-component="cta"', false)
            ->assertSee('data-section-component="media-embed"', false)
            ->assertSee('data-section-component="stats"', false)
            ->assertSee('data-section-component="testimonials"', false)
            ->assertSee('data-section-component="faq"', false)
            ->assertSee('<h1', false)
            ->assertSee('<iframe', false)
            ->assertSee('<details', false)
            ->assertSee('href="/destination"', false);
    }

    public function test_section_registry_rejects_unsupported_theme_and_layout_combinations(): void
    {
        config()->set('pages', [
            'invalid-sections' => [
                'path' => '/invalid-sections',
                'sections' => [
                    [
                        'component' => 'hero',
                        'theme' => 'not-a-theme',
                        'layout' => 'not-a-layout',
                    ],
                ],
            ],
        ]);

        $errors = app(PageRepository::class)->validationErrors();

        $this->assertCount(2, $errors);
    }

    public function test_section_actions_reject_unsafe_urls(): void
    {
        $this->assertInvalidArgumentDuringBladeRender(
            fn () => Blade::render(
                '<x-section-actions :actions="$actions" />',
                [
                    'actions' => [
                        [
                            'label' => 'Fixture action',
                            'url' => 'javascript:alert(1)',
                        ],
                    ],
                ],
            )
        );
    }

    public function test_section_images_require_an_explicit_alt_contract(): void
    {
        $this->assertInvalidArgumentDuringBladeRender(
            fn () => Blade::render(
                '<x-section-image :image="$image" />',
                [
                    'image' => [
                        'asset' => 'fixture',
                    ],
                ],
            )
        );
    }

    public function test_embedded_media_rejects_non_http_sources(): void
    {
        $this->assertInvalidArgumentDuringBladeRender(
            fn () => Blade::render(
                '<x-embed-frame src="javascript:alert(1)" title="Fixture" />'
            )
        );
    }


    private function assertInvalidArgumentDuringBladeRender(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            $current = $exception;

            while ($current !== null) {
                if ($current instanceof InvalidArgumentException) {
                    $this->assertInstanceOf(
                        InvalidArgumentException::class,
                        $current
                    );

                    return;
                }

                $current = $current->getPrevious();
            }

            throw $exception;
        }

        $this->fail(
            'Expected Blade rendering to fail because of an invalid argument.'
        );
    }

    public function test_client_view_namespace_can_override_a_registered_reusable_section(): void
    {
        $this->temporaryClientViewPath = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-section-override-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists(
            $this->temporaryClientViewPath.'/sections'
        );

        File::put(
            $this->temporaryClientViewPath.'/sections/hero.blade.php',
            '<section></section>'
        );

        View::addNamespace(
            'client',
            $this->temporaryClientViewPath
        );

        $this->assertSame(
            'client::sections.hero',
            app(SectionManager::class)->viewFor('hero')
        );
    }
}