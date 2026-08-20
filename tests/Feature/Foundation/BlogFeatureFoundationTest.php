<?php

namespace Tests\Feature\Foundation;

use App\Features\Blog\BlogContentNormalizer;
use App\Features\Blog\BlogServiceProvider;
use App\Features\Blog\BlogSetupValidator;
use App\Features\Blog\BlogSitemapContributor;
use App\Features\Blog\Models\BlogCategory;
use App\Features\Blog\Models\BlogPost;
use App\Support\Editorial\EditorialPromotionRegistry;
use App\Support\Seo\Migration\SeoMigrationAuditor;
use App\Support\Seo\SeoExtensionRegistry;
use App\Support\SetupValidation\SetupValidationRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\DatabaseTestCase;

class BlogFeatureFoundationTest extends DatabaseTestCase
{
    private ?string $temporaryRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('app.url', 'https://site.example.test');
        config()->set('site.name', 'Fixture Site');
        config()->set('features.blog.path', '/blog');
        config()->set('features.blog.posts_per_page', 12);
        config()->set('features.blog.category_indexable', true);
        config()->set('features.blog.index', [
            'title' => 'Fixture archive',
            'indexable' => true,
            'featured_limit' => 4,
        ]);
        config()->set('pages', []);
        config()->set('site.seo.redirects', []);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('blog_category_post');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');

        if ($this->temporaryRoot !== null) {
            File::deleteDirectory($this->temporaryRoot);
        }

        parent::tearDown();
    }

    public function test_blog_is_registered_as_an_optional_feature(): void
    {
        $definition = config('features.available.blog');

        $this->assertIsArray($definition);
        $this->assertSame(
            BlogServiceProvider::class,
            $definition['provider'] ?? null,
        );
        $this->assertFalse(Route::has('blog.index'));
    }

    public function test_enabled_blog_provider_registers_routes_seo_and_setup_contributors(): void
    {
        $this->bootBlogFeature();

        $this->assertTrue(Route::has('blog.index'));
        $this->assertTrue(Route::has('blog.category'));
        $this->assertTrue(Route::has('blog.show'));

        $this->assertContains(
            BlogSitemapContributor::class,
            array_map(
                static fn (object $contributor): string => $contributor::class,
                app(SeoExtensionRegistry::class)->sitemapContributors(),
            ),
        );

        $this->assertContains(
            BlogSetupValidator::class,
            array_map(
                static fn (object $contributor): string => $contributor::class,
                app(SetupValidationRegistry::class)->contributors(),
            ),
        );
    }

    public function test_public_archive_excludes_drafts_and_future_posts(): void
    {
        $this->bootBlogFeature();
        $this->createBlogTables();

        $this->createPost('published-post', now()->subHour());
        $this->createPost('draft-post', null);
        $this->createPost('future-post', now()->addHour());

        $this->get('/blog')
            ->assertOk()
            ->assertSee('href="/blog/published-post"', false)
            ->assertDontSee('href="/blog/draft-post"', false)
            ->assertDontSee('href="/blog/future-post"', false);
    }

    public function test_unpublished_article_returns_not_found(): void
    {
        $this->bootBlogFeature();
        $this->createBlogTables();

        $this->createPost('draft-post', null);

        $this->get('/blog/draft-post')
            ->assertNotFound();
    }

    public function test_article_renders_structured_content_as_escaped_semantic_html(): void
    {
        $this->bootBlogFeature();
        $this->createBlogTables();

        BlogPost::query()->create([
            'slug' => 'published-post',
            'title' => 'Fixture',
            'content' => [
                [
                    'type' => 'paragraph',
                    'text' => '<script>alert(1)</script>',
                ],
                [
                    'type' => 'heading',
                    'level' => 2,
                    'text' => 'Fixture heading',
                ],
                [
                    'type' => 'links',
                    'items' => [
                        [
                            'label' => 'Fixture link',
                            'url' => '/destination',
                        ],
                    ],
                ],
            ],
            'published_at' => now()->subHour(),
        ]);

        $this->get('/blog/published-post')
            ->assertOk()
            ->assertSee('data-blog-page="article"', false)
            ->assertSee('<article', false)
            ->assertSee('<h2>', false)
            ->assertSee('href="/destination"', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_category_route_filters_published_posts(): void
    {
        $this->bootBlogFeature();
        $this->createBlogTables();

        $firstCategory = BlogCategory::query()->create([
            'slug' => 'first-category',
            'name' => 'Fixture A',
        ]);
        $secondCategory = BlogCategory::query()->create([
            'slug' => 'second-category',
            'name' => 'Fixture B',
        ]);

        $first = $this->createPost('first-post', now()->subHour());
        $second = $this->createPost('second-post', now()->subHour());

        $first->categories()->attach($firstCategory);
        $second->categories()->attach($secondCategory);

        $this->get('/blog/category/first-category')
            ->assertOk()
            ->assertSee('href="/blog/first-post"', false)
            ->assertDontSee('href="/blog/second-post"', false);
    }

    public function test_sitemap_contribution_excludes_drafts_and_nonindexable_posts(): void
    {
        $this->bootBlogFeature();
        $this->createBlogTables();

        $this->createPost('published-post', now()->subHour());
        $this->createPost('draft-post', null);
        $this->createPost(
            'private-post',
            now()->subHour(),
            false,
        );

        $locations = array_column(
            app(BlogSitemapContributor::class)->sitemapEntries(),
            'loc',
        );

        $this->assertContains(
            'https://site.example.test/blog',
            $locations,
        );
        $this->assertContains(
            'https://site.example.test/blog/published-post',
            $locations,
        );
        $this->assertNotContains(
            'https://site.example.test/blog/draft-post',
            $locations,
        );
        $this->assertNotContains(
            'https://site.example.test/blog/private-post',
            $locations,
        );
    }

    public function test_old_platform_audit_accepts_redirect_to_indexable_blog_article(): void
    {
        $this->bootBlogFeature();
        $this->createBlogTables();
        $this->createPost('replacement-post', now()->subHour());

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-blog-migration-'.bin2hex(random_bytes(6));

        $migrationDirectory = $this->temporaryRoot
            .'/clients/blog-test-client/resources/migration';

        File::ensureDirectoryExists($migrationDirectory);
        File::put(
            $migrationDirectory.'/legacy-urls.tsv',
            "path\toutcome\ttarget\tnotes\n/old-post\tredirected\t/blog/replacement-post\t\n",
        );

        config()->set('client.key', 'blog-test-client');
        config()->set('seo_migration.enabled', true);
        config()->set(
            'seo_migration.inventory_path',
            'resources/migration/legacy-urls.tsv',
        );
        config()->set('site.seo.redirects', [
            [
                'from' => '/old-post',
                'to' => '/blog/replacement-post',
                'status' => 301,
            ],
        ]);

        $result = app(SeoMigrationAuditor::class)
            ->audit($this->temporaryRoot);

        $this->assertTrue($result->valid());
        $this->assertSame([], $result->errors);
    }

    public function test_blog_setup_validation_rejects_static_page_and_redirect_collisions(): void
    {
        $this->bootBlogFeature();

        config()->set('pages', [
            'conflict' => [
                'path' => '/blog/static-page',
                'sections' => [],
            ],
        ]);
        config()->set('site.seo.redirects', [
            [
                'from' => '/blog/old-post',
                'to' => '/replacement',
                'status' => 301,
            ],
        ]);

        $errors = app(BlogSetupValidator::class)
            ->validationErrors();

        $this->assertNotEmpty($errors);
    }

    public function test_blog_content_rejects_unknown_block_types(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(BlogContentNormalizer::class)->normalize([
            [
                'type' => 'raw-html',
                'html' => '<strong>unsafe contract</strong>',
            ],
        ]);
    }

    private function bootBlogFeature(): void
    {
        $provider = new BlogServiceProvider(app());

        $provider->register();
        $provider->boot(
            app(SetupValidationRegistry::class),
            app(SeoExtensionRegistry::class),
            app(EditorialPromotionRegistry::class),
        );

        Route::getRoutes()->refreshNameLookups();
    }

    private function createBlogTables(): void
    {
        $migration = require base_path(
            'app/Features/Blog/database/migrations/2026_08_19_000000_create_blog_tables.php'
        );

        $migration->up();
    }

    private function createPost(
        string $slug,
        mixed $publishedAt,
        bool $indexable = true,
    ): BlogPost {
        return BlogPost::query()->create([
            'slug' => $slug,
            'title' => 'Fixture',
            'content' => [
                [
                    'type' => 'paragraph',
                    'text' => 'Fixture body',
                ],
            ],
            'indexable' => $indexable,
            'published_at' => $publishedAt,
        ]);
    }
}