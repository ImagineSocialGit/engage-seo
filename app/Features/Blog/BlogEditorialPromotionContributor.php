<?php

namespace App\Features\Blog;

use App\Contracts\Editorial\EditorialPromotionContributor;
use App\Features\Blog\Models\BlogCategory;
use App\Features\Blog\Models\BlogPost;
use DateTimeImmutable;
use App\Support\Media\MediaAssetResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class BlogEditorialPromotionContributor implements EditorialPromotionContributor
{
    private const VERSION = 1;

    public function __construct(
        private readonly BlogContentNormalizer $content,
        private readonly MediaAssetResolver $media,
    ) {
    }

    public function key(): string
    {
        return 'blog';
    }

    public function exportSection(): array
    {
        $this->assertTablesExist();

        if (config('app.env') === 'production'
            && BlogPost::query()->whereNull('published_at')->exists()
        ) {
            throw new RuntimeException(
                'Production Blog contains draft posts. Production is not an editorial drafting environment; resolve those rows before promotion or rollback export.'
            );
        }

        $posts = BlogPost::query()
            ->whereNotNull('published_at')
            ->with('categories')
            ->orderBy('slug')
            ->get();

        $categories = [];
        $postRows = [];

        foreach ($posts as $post) {
            $categorySlugs = $post->categories
                ->pluck('slug')
                ->filter(fn (mixed $slug): bool => is_string($slug) && trim($slug) !== '')
                ->map(fn (string $slug): string => trim($slug))
                ->unique()
                ->sort()
                ->values()
                ->all();

            foreach ($post->categories as $category) {
                $categories[$category->slug] = $this->exportCategory($category);
            }

            $postRows[] = $this->exportPost($post, $categorySlugs);
        }

        ksort($categories);

        $section = [
            'version' => self::VERSION,
            'categories' => array_values($categories),
            'posts' => $postRows,
        ];

        $errors = $this->validationErrors($section);

        if ($errors !== []) {
            throw new RuntimeException(
                "Blog editorial state is not safe to export:\n- ".implode("\n- ", $errors)
            );
        }

        return $section;
    }

    public function validationErrors(array $section): array
    {
        $errors = [];

        foreach (['blog_categories', 'blog_posts', 'blog_category_post'] as $table) {
            if (! Schema::hasTable($table)) {
                $errors[] = "Target Blog table [{$table}] is missing; run migrations before validating or importing editorial state.";
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $unknown = array_values(array_diff(
            array_keys($section),
            ['version', 'categories', 'posts'],
        ));

        if ($unknown !== []) {
            sort($unknown);
            $errors[] = 'Blog snapshot contains unsupported key(s): '.implode(', ', $unknown).'.';
        }

        if (($section['version'] ?? null) !== self::VERSION) {
            $errors[] = 'Blog snapshot version is unsupported.';
        }

        $categories = $section['categories'] ?? null;
        $posts = $section['posts'] ?? null;

        if (! is_array($categories) || ! array_is_list($categories)) {
            $errors[] = 'Blog snapshot [categories] must be a list.';
            $categories = [];
        }

        if (! is_array($posts) || ! array_is_list($posts)) {
            $errors[] = 'Blog snapshot [posts] must be a list.';
            $posts = [];
        }

        $categorySlugs = [];

        foreach ($categories as $index => $category) {
            if (! is_array($category)) {
                $errors[] = "Blog snapshot category [{$index}] must be an object.";
                continue;
            }

            $errors = [
                ...$errors,
                ...$this->categoryErrors($category, $index),
            ];

            $slug = $category['slug'] ?? null;

            if (is_string($slug) && $this->validSlug($slug)) {
                if (array_key_exists($slug, $categorySlugs)) {
                    $errors[] = "Blog snapshot contains duplicate category slug [{$slug}].";
                }

                $categorySlugs[$slug] = false;
            }
        }

        $postSlugs = [];

        foreach ($posts as $index => $post) {
            if (! is_array($post)) {
                $errors[] = "Blog snapshot post [{$index}] must be an object.";
                continue;
            }

            $errors = [
                ...$errors,
                ...$this->postErrors($post, $index),
            ];

            $slug = $post['slug'] ?? null;

            if (is_string($slug) && $this->validSlug($slug)) {
                if (array_key_exists($slug, $postSlugs)) {
                    $errors[] = "Blog snapshot contains duplicate post slug [{$slug}].";
                }

                $postSlugs[$slug] = true;
            }

            $relations = $post['category_slugs'] ?? [];

            if (! is_array($relations) || ! array_is_list($relations)) {
                continue;
            }

            $seenRelations = [];

            foreach ($relations as $relationIndex => $categorySlug) {
                if (! is_string($categorySlug) || ! $this->validSlug($categorySlug)) {
                    $errors[] = "Blog snapshot post [{$index}.category_slugs.{$relationIndex}] must be a valid category slug.";
                    continue;
                }

                if (array_key_exists($categorySlug, $seenRelations)) {
                    $errors[] = "Blog snapshot post [{$index}] repeats category slug [{$categorySlug}].";
                    continue;
                }

                $seenRelations[$categorySlug] = true;

                if (! array_key_exists($categorySlug, $categorySlugs)) {
                    $errors[] = "Blog snapshot post [{$index}] references missing category [{$categorySlug}].";
                    continue;
                }

                $categorySlugs[$categorySlug] = true;
            }
        }

        foreach ($categorySlugs as $slug => $referenced) {
            if (! $referenced) {
                $errors[] = "Blog snapshot category [{$slug}] is not attached to any promotable post.";
            }
        }

        return array_values(array_unique($errors));
    }

    public function applySection(array $section): void
    {
        $errors = $this->validationErrors($section);

        if ($errors !== []) {
            throw new InvalidArgumentException(
                "Blog editorial snapshot is invalid:\n- ".implode("\n- ", $errors)
            );
        }

        DB::table('blog_category_post')->delete();
        DB::table('blog_posts')->delete();
        DB::table('blog_categories')->delete();

        $categoryIds = [];

        foreach ($section['categories'] as $category) {
            $categoryIds[$category['slug']] = DB::table('blog_categories')->insertGetId([
                'slug' => $category['slug'],
                'name' => $category['name'],
                'description' => $category['description'],
                'meta_title' => $category['meta_title'],
                'meta_description' => $category['meta_description'],
                'indexable' => $category['indexable'],
                'sort_order' => $category['sort_order'],
                'created_at' => new DateTimeImmutable($category['created_at']),
                'updated_at' => new DateTimeImmutable($category['updated_at']),
            ]);
        }

        foreach ($section['posts'] as $post) {
            try {
                $content = json_encode(
                    $post['content'],
                    JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                        | JSON_THROW_ON_ERROR,
                );
            } catch (JsonException $exception) {
                throw new RuntimeException(
                    "Unable to encode Blog post [{$post['slug']}] content: {$exception->getMessage()}"
                );
            }

            $postId = DB::table('blog_posts')->insertGetId([
                'slug' => $post['slug'],
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'content' => $content,
                'author_name' => $post['author_name'],
                'featured' => $post['featured'],
                'featured_image_asset' => $post['featured_image_asset'],
                'featured_image_alt' => $post['featured_image_alt'],
                'meta_title' => $post['meta_title'],
                'meta_description' => $post['meta_description'],
                'indexable' => $post['indexable'],
                'published_at' => new DateTimeImmutable($post['published_at']),
                'created_at' => new DateTimeImmutable($post['created_at']),
                'updated_at' => new DateTimeImmutable($post['updated_at']),
            ]);

            foreach ($post['category_slugs'] as $categorySlug) {
                DB::table('blog_category_post')->insert([
                    'blog_category_id' => $categoryIds[$categorySlug],
                    'blog_post_id' => $postId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exportCategory(BlogCategory $category): array
    {
        return [
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'indexable' => (bool) $category->indexable,
            'sort_order' => (int) $category->sort_order,
            'created_at' => $category->created_at?->utc()->toIso8601String(),
            'updated_at' => $category->updated_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @param list<string> $categorySlugs
     * @return array<string, mixed>
     */
    private function exportPost(BlogPost $post, array $categorySlugs): array
    {
        return [
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'author_name' => $post->author_name,
            'featured' => (bool) $post->featured,
            'featured_image_asset' => $post->featured_image_asset,
            'featured_image_alt' => $post->featured_image_alt,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'indexable' => (bool) $post->indexable,
            'published_at' => $post->published_at?->utc()->toIso8601String(),
            'created_at' => $post->created_at?->utc()->toIso8601String(),
            'updated_at' => $post->updated_at?->utc()->toIso8601String(),
            'category_slugs' => $categorySlugs,
        ];
    }

    /**
     * @param array<string, mixed> $category
     * @return list<string>
     */
    private function categoryErrors(array $category, int $index): array
    {
        $errors = $this->unknownKeys(
            $category,
            [
                'slug',
                'name',
                'description',
                'meta_title',
                'meta_description',
                'indexable',
                'sort_order',
                'created_at',
                'updated_at',
            ],
            "Blog snapshot category [{$index}]",
        );

        $slug = $category['slug'] ?? null;

        if (! is_string($slug) || ! $this->validSlug($slug)) {
            $errors[] = "Blog snapshot category [{$index}.slug] must use lowercase letters, numbers, and hyphens.";
        }

        if (! $this->nonBlankString($category['name'] ?? null)) {
            $errors[] = "Blog snapshot category [{$index}.name] must be a non-blank string.";
        }

        foreach (['description', 'meta_title', 'meta_description'] as $key) {
            if (! $this->nullableString($category[$key] ?? null)) {
                $errors[] = "Blog snapshot category [{$index}.{$key}] must be null or a string.";
            }
        }

        if (! is_bool($category['indexable'] ?? null)) {
            $errors[] = "Blog snapshot category [{$index}.indexable] must be a boolean.";
        }

        $sortOrder = $category['sort_order'] ?? null;

        if (! is_int($sortOrder) || $sortOrder < 0 || $sortOrder > 65535) {
            $errors[] = "Blog snapshot category [{$index}.sort_order] must be an integer between 0 and 65535.";
        }

        foreach (['created_at', 'updated_at'] as $key) {
            if (! $this->validDateString($category[$key] ?? null)) {
                $errors[] = "Blog snapshot category [{$index}.{$key}] must be a valid timestamp string.";
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $post
     * @return list<string>
     */
    private function postErrors(array $post, int $index): array
    {
        $errors = $this->unknownKeys(
            $post,
            [
                'slug',
                'title',
                'excerpt',
                'content',
                'author_name',
                'featured',
                'featured_image_asset',
                'featured_image_alt',
                'meta_title',
                'meta_description',
                'indexable',
                'published_at',
                'created_at',
                'updated_at',
                'category_slugs',
            ],
            "Blog snapshot post [{$index}]",
        );

        $slug = $post['slug'] ?? null;

        if (! is_string($slug) || ! $this->validSlug($slug)) {
            $errors[] = "Blog snapshot post [{$index}.slug] must use lowercase letters, numbers, and hyphens.";
        }

        if (! $this->nonBlankString($post['title'] ?? null)) {
            $errors[] = "Blog snapshot post [{$index}.title] must be a non-blank string.";
        }

        foreach (['excerpt', 'author_name', 'meta_title', 'meta_description'] as $key) {
            if (! $this->nullableString($post[$key] ?? null)) {
                $errors[] = "Blog snapshot post [{$index}.{$key}] must be null or a string.";
            }
        }

        foreach (['featured', 'indexable'] as $key) {
            if (! is_bool($post[$key] ?? null)) {
                $errors[] = "Blog snapshot post [{$index}.{$key}] must be a boolean.";
            }
        }

        $asset = $post['featured_image_asset'] ?? null;
        $alt = $post['featured_image_alt'] ?? null;

        if (! $this->nullableString($asset)) {
            $errors[] = "Blog snapshot post [{$index}.featured_image_asset] must be null or a string.";
        }

        if ($asset !== null && (! is_string($asset) || trim($asset) === '')) {
            $errors[] = "Blog snapshot post [{$index}.featured_image_asset] must be null or a non-blank string.";
        }

        if ($asset !== null && ! is_string($alt)) {
            $errors[] = "Blog snapshot post [{$index}.featured_image_alt] must be explicitly provided as a string when a featured image is configured.";
        } elseif ($asset === null && $alt !== null) {
            $errors[] = "Blog snapshot post [{$index}.featured_image_alt] must be null when no featured image is configured.";
        }

        if (is_string($asset) && trim($asset) !== '') {
            try {
                $this->media->resolve(trim($asset));
            } catch (Throwable $exception) {
                $errors[] = "Blog snapshot post [{$index}.featured_image_asset] is unavailable on the target: {$exception->getMessage()}";
            }
        }

        $content = $post['content'] ?? null;

        try {
            $normalizedContent = $this->content->normalize($content);

            foreach ($normalizedContent as $blockIndex => $block) {
                if (($block['type'] ?? null) !== 'image') {
                    continue;
                }

                try {
                    $this->media->resolve($block['asset']);
                } catch (Throwable $exception) {
                    $errors[] = "Blog snapshot post [{$index}.content.{$blockIndex}.asset] is unavailable on the target: {$exception->getMessage()}";
                }
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = "Blog snapshot post [{$index}.content] is invalid: {$exception->getMessage()}";
        }

        foreach (['published_at', 'created_at', 'updated_at'] as $key) {
            if (! $this->validDateString($post[$key] ?? null)) {
                $errors[] = "Blog snapshot post [{$index}.{$key}] must be a valid timestamp string.";
            }
        }

        $relations = $post['category_slugs'] ?? null;

        if (! is_array($relations) || ! array_is_list($relations)) {
            $errors[] = "Blog snapshot post [{$index}.category_slugs] must be a list.";
        }

        return $errors;
    }

    private function assertTablesExist(): void
    {
        foreach (['blog_categories', 'blog_posts', 'blog_category_post'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Blog table [{$table}] is missing; run migrations before exporting editorial state."
                );
            }
        }
    }

    private function validSlug(string $value): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }

    private function nonBlankString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function nullableString(mixed $value): bool
    {
        return $value === null || is_string($value);
    }

    private function validDateString(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            new DateTimeImmutable($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $allowed
     * @return list<string>
     */
    private function unknownKeys(
        array $values,
        array $allowed,
        string $context,
    ): array {
        $unknown = array_values(array_diff(array_keys($values), $allowed));

        if ($unknown === []) {
            return [];
        }

        sort($unknown);

        return [
            $context.' contains unsupported key(s): '.implode(', ', $unknown).'.',
        ];
    }
}