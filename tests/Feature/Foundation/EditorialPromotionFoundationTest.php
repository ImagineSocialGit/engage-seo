<?php

namespace Tests\Feature\Foundation;

use App\Features\Blog\BlogEditorialPromotionContributor;
use App\Features\Blog\BlogServiceProvider;
use App\Features\Blog\Models\BlogCategory;
use App\Features\Blog\Models\BlogPost;
use App\Support\Editorial\EditorialPromotionPolicy;
use App\Support\Editorial\EditorialPromotionRegistry;
use App\Support\Editorial\EditorialPromotionService;
use App\Support\Editorial\EditorialSnapshotCodec;
use App\Support\Seo\SeoExtensionRegistry;
use App\Support\SetupValidation\SetupValidationRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\DatabaseTestCase;

class EditorialPromotionFoundationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('client.key', 'promotion-test-client');
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

        $this->bootBlogFeature();
        $this->createBlogTables();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('blog_category_post');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');

        parent::tearDown();
    }

    public function test_editorial_promotion_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('editorial:export', $commands);
        $this->assertArrayHasKey('editorial:validate', $commands);
        $this->assertArrayHasKey('editorial:import', $commands);
    }

    public function test_enabled_blog_registers_as_an_editorial_promotion_contributor(): void
    {
        $contributors = app(EditorialPromotionRegistry::class)
            ->keyedContributors();

        $this->assertArrayHasKey('blog', $contributors);
        $this->assertInstanceOf(
            BlogEditorialPromotionContributor::class,
            $contributors['blog'],
        );
    }

    public function test_export_includes_published_and_scheduled_posts_but_excludes_drafts(): void
    {
        $category = BlogCategory::query()->create([
            'slug' => 'fixture-category',
            'name' => 'Fixture category',
        ]);

        $published = $this->createPost(
            'published-post',
            now()->subHour(),
        );
        $scheduled = $this->createPost(
            'scheduled-post',
            now()->addDay(),
        );
        $draft = $this->createPost(
            'draft-post',
            null,
        );

        $published->categories()->attach($category);
        $scheduled->categories()->attach($category);
        $draft->categories()->attach($category);

        $document = app(EditorialPromotionService::class)
            ->exportDocument();

        $this->assertSame(
            EditorialSnapshotCodec::FORMAT,
            $document['format'],
        );
        $this->assertSame(
            'promotion-test-client',
            $document['client_key'],
        );
        $this->assertSame('testing', $document['source_environment']);
        $this->assertSame(
            ['published-post', 'scheduled-post'],
            array_column($document['sections']['blog']['posts'], 'slug'),
        );
        $this->assertSame(
            ['fixture-category'],
            array_column($document['sections']['blog']['categories'], 'slug'),
        );
        $this->assertStringStartsWith('sha256:', $document['checksum']);
    }

    public function test_snapshot_checksum_detects_modified_editorial_payload(): void
    {
        $this->createPost('published-post', now()->subHour());

        $codec = app(EditorialSnapshotCodec::class);
        $document = app(EditorialPromotionService::class)
            ->exportDocument();

        $document['sections']['blog']['posts'][0]['title'] = 'Tampered';

        $this->expectException(InvalidArgumentException::class);

        $codec->decode(json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    public function test_snapshot_validation_rejects_a_different_client(): void
    {
        $this->createPost('published-post', now()->subHour());

        $document = app(EditorialPromotionService::class)
            ->exportDocument();
        $document['client_key'] = 'another-client';

        $errors = app(EditorialPromotionService::class)
            ->validationErrors($document);

        $this->assertNotEmpty($errors);
    }

    public function test_apply_replaces_only_promotable_blog_state_from_snapshot(): void
    {
        $category = BlogCategory::query()->create([
            'slug' => 'fixture-category',
            'name' => 'Fixture category',
        ]);

        $published = $this->createPost(
            'published-post',
            now()->subHour(),
        );
        $scheduled = $this->createPost(
            'scheduled-post',
            now()->addDay(),
        );
        $this->createPost('draft-post', null);

        $published->categories()->attach($category);
        $scheduled->categories()->attach($category);

        $promotion = app(EditorialPromotionService::class);
        $document = $promotion->exportDocument();

        BlogPost::query()->delete();
        BlogCategory::query()->delete();

        $this->createPost('target-only-post', now()->subHour());

        $promotion->apply($document);

        $this->assertSame(
            ['published-post', 'scheduled-post'],
            BlogPost::query()->orderBy('slug')->pluck('slug')->all(),
        );
        $this->assertSame(
            ['fixture-category'],
            BlogCategory::query()->orderBy('slug')->pluck('slug')->all(),
        );
        $this->assertSame(
            ['fixture-category'],
            BlogPost::query()
                ->where('slug', 'published-post')
                ->firstOrFail()
                ->categories
                ->pluck('slug')
                ->all(),
        );
    }

    public function test_production_import_policy_accepts_staging_or_production_backup_only(): void
    {
        config()->set('app.env', 'production');
        $policy = app(EditorialPromotionPolicy::class);

        $this->assertNotEmpty($policy->importErrors([
            'source_environment' => 'local',
        ]));
        $this->assertSame([], $policy->importErrors([
            'source_environment' => 'staging',
        ]));
        $this->assertSame([], $policy->importErrors([
            'source_environment' => 'production',
        ]));
    }

    public function test_staging_import_policy_rejects_snapshot_imports(): void
    {
        config()->set('app.env', 'staging');

        $errors = app(EditorialPromotionPolicy::class)
            ->importErrors([
                'source_environment' => 'staging',
            ]);

        $this->assertNotEmpty($errors);
    }

    public function test_production_export_refuses_unpublished_drafts(): void
    {
        $this->createPost('draft-post', null);
        config()->set('app.env', 'production');

        $this->expectException(RuntimeException::class);

        app(EditorialPromotionService::class)
            ->exportDocument();
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
            'published_at' => $publishedAt,
        ]);
    }
}