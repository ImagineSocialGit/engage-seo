<?php

namespace Tests\Feature\Foundation;

use App\Support\Pages\PageRepository;
use App\Support\Views\ClientViewNamespaceRegistrar;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PublicPageRenderingFoundationTest extends TestCase
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

    public function test_configured_public_page_resolves_to_normalized_view_contract(): void
    {
        config()->set('app.url', 'https://site.example.test');

        config()->set('pages', [
            'example' => [
                'path' => '/example',
                'meta' => [
                    'title' => 'Example title',
                    'description' => 'Example description',
                    'indexable' => false,
                ],
                'sections' => [
                    [
                        'id' => 'example-section',
                        'component' => 'content',
                        'props' => [
                            'title' => 'Section title',
                            'content' => [
                                'Section body',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->get('/example');

        $response
            ->assertOk()
            ->assertViewIs('pages.public')
            ->assertViewHas('page', function (array $page): bool {
                return $page['key'] === 'example'
                    && $page['path'] === '/example'
                    && $page['meta']['robots'] === 'noindex,nofollow'
                    && $page['meta']['canonical'] === 'https://site.example.test/example'
                    && $page['sections'][0]['component'] === 'content'
                    && $page['sections'][0]['id'] === 'example-section'
                    && $page['sections'][0]['theme'] === null
                    && $page['sections'][0]['layout'] === null
                    && $page['sections'][0]['overrides'] === [];
            });
    }

    public function test_unknown_public_page_returns_not_found(): void
    {
        config()->set('client.key', 'configured-client');
        config()->set('pages', []);

        $this->get('/not-configured')
            ->assertNotFound();
    }

    public function test_duplicate_page_paths_are_validation_errors(): void
    {
        config()->set('pages', [
            'first' => [
                'path' => '/same-path',
                'sections' => [],
            ],
            'second' => [
                'path' => '/same-path/',
                'sections' => [],
            ],
        ]);

        $errors = app(PageRepository::class)->validationErrors();

        $this->assertCount(1, $errors);
    }

    public function test_client_public_page_view_is_selected_when_explicit_override_exists(): void
    {
        $this->temporaryClientViewPath = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-client-views-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists(
            $this->temporaryClientViewPath.'/pages'
        );

        File::put(
            $this->temporaryClientViewPath.'/pages/public.blade.php',
            <<<'BLADE'
<x-layouts.public :meta="$page['meta']">
</x-layouts.public>
BLADE
        );

        View::addNamespace(
            'client',
            $this->temporaryClientViewPath
        );

        config()->set('pages', [
            'example' => [
                'path' => '/client-view',
                'sections' => [],
            ],
        ]);

        $this->get('/client-view')
            ->assertOk()
            ->assertViewIs('client::pages.public');
    }
}