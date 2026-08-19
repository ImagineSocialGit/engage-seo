<?php

namespace App\Features\Blog;

use App\Support\Site\SiteLinkNormalizer;
use InvalidArgumentException;

final class BlogConfiguration
{
    private const RESERVED_BASE_PATHS = [
        '/',
        '/robots.txt',
        '/sitemap.xml',
        '/up',
    ];

    public function __construct(
        private readonly SiteLinkNormalizer $links,
    ) {
    }

    public function basePath(): string
    {
        $config = $this->configuration();
        $path = $config['path'] ?? '/blog';

        if (! is_string($path)) {
            throw new InvalidArgumentException(
                'Blog Feature [path] must be a string.'
            );
        }

        $path = trim($path);

        if ($path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '?')
            || str_contains($path, '#')
            || str_contains($path, '\\')
        ) {
            throw new InvalidArgumentException(
                'Blog Feature [path] must be an absolute site path without a query string, fragment, or backslash.'
            );
        }

        $path = $path === '/'
            ? '/'
            : '/'.trim($path, '/');

        if (in_array($path, self::RESERVED_BASE_PATHS, true)) {
            throw new InvalidArgumentException(
                "Blog Feature [path] may not use reserved platform path [{$path}]."
            );
        }

        foreach (explode('/', trim($path, '/')) as $segment) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $segment) !== 1) {
                throw new InvalidArgumentException(
                    'Blog Feature [path] segments must use lowercase letters, numbers, and hyphens.'
                );
            }
        }

        return $path;
    }

    public function articlePath(string $slug): string
    {
        $slug = $this->slug($slug, 'Blog post slug');

        return $this->basePath().'/'.$slug;
    }

    public function categoryPath(string $slug): string
    {
        return $this->basePath().'/category/'
            .$this->slug($slug, 'Blog category slug');
    }

    public function postsPerPage(): int
    {
        $config = $this->configuration();
        $value = $config['posts_per_page'] ?? 12;

        if (! is_int($value) || $value < 1 || $value > 50) {
            throw new InvalidArgumentException(
                'Blog Feature [posts_per_page] must be an integer between 1 and 50.'
            );
        }

        return $value;
    }

    public function categoryIndexable(): bool
    {
        $config = $this->configuration();
        $value = $config['category_indexable'] ?? true;

        if (! is_bool($value)) {
            throw new InvalidArgumentException(
                'Blog Feature [category_indexable] must be a boolean.'
            );
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $config = $this->configuration();
        $index = $config['index'] ?? [];

        if (! is_array($index)) {
            throw new InvalidArgumentException(
                'Blog Feature [index] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $index,
            [
                'title',
                'meta_title',
                'meta_description',
                'eyebrow',
                'intro',
                'actions',
                'indexable',
                'featured_limit',
                'featured_title',
                'categories_title',
                'footer_cta',
            ],
            'Blog Feature [index]',
        );

        $indexable = $index['indexable'] ?? true;

        if (! is_bool($indexable)) {
            throw new InvalidArgumentException(
                'Blog Feature [index.indexable] must be a boolean.'
            );
        }

        $featuredLimit = $index['featured_limit'] ?? 4;

        if (! is_int($featuredLimit) || $featuredLimit < 0 || $featuredLimit > 12) {
            throw new InvalidArgumentException(
                'Blog Feature [index.featured_limit] must be an integer between 0 and 12.'
            );
        }

        return [
            'title' => $this->requiredString(
                $index['title'] ?? 'Blog',
                'Blog Feature [index.title]',
            ),
            'meta_title' => $this->optionalString(
                $index['meta_title'] ?? null,
                'Blog Feature [index.meta_title]',
            ),
            'meta_description' => $this->optionalString(
                $index['meta_description'] ?? null,
                'Blog Feature [index.meta_description]',
            ),
            'eyebrow' => $this->optionalString(
                $index['eyebrow'] ?? null,
                'Blog Feature [index.eyebrow]',
            ),
            'intro' => $this->optionalString(
                $index['intro'] ?? null,
                'Blog Feature [index.intro]',
            ),
            'actions' => $this->links->links(
                $index['actions'] ?? [],
                $this->basePath(),
                'features.blog.index.actions',
                ['http', 'https', 'mailto', 'tel'],
                true,
                false,
            ),
            'indexable' => $indexable,
            'featured_limit' => $featuredLimit,
            'featured_title' => $this->optionalString(
                $index['featured_title'] ?? null,
                'Blog Feature [index.featured_title]',
            ),
            'categories_title' => $this->optionalString(
                $index['categories_title'] ?? null,
                'Blog Feature [index.categories_title]',
            ),
            'footer_cta' => $this->footerCta(
                $index['footer_cta'] ?? null,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];

        foreach ([
            fn (): string => $this->basePath(),
            fn (): int => $this->postsPerPage(),
            fn (): bool => $this->categoryIndexable(),
            fn (): array => $this->index(),
        ] as $check) {
            try {
                $check();
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        $config = config('features.blog', []);

        if (! is_array($config)) {
            throw new InvalidArgumentException(
                'Blog Feature configuration must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $config,
            [
                'path',
                'posts_per_page',
                'category_indexable',
                'index',
            ],
            'Blog Feature configuration',
        );

        return $config;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function footerCta(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(
                'Blog Feature [index.footer_cta] must be null or an array.'
            );
        }

        $this->assertOnlyKeys(
            $value,
            ['title', 'description', 'actions'],
            'Blog Feature [index.footer_cta]',
        );

        $actions = $this->links->links(
            $value['actions'] ?? [],
            $this->basePath(),
            'features.blog.index.footer_cta.actions',
            ['http', 'https', 'mailto', 'tel'],
            true,
            false,
        );

        if ($actions === []) {
            throw new InvalidArgumentException(
                'Blog Feature [index.footer_cta.actions] must contain at least one action.'
            );
        }

        return [
            'title' => $this->requiredString(
                $value['title'] ?? null,
                'Blog Feature [index.footer_cta.title]',
            ),
            'description' => $this->optionalString(
                $value['description'] ?? null,
                'Blog Feature [index.footer_cta.description]',
            ),
            'actions' => $actions,
        ];
    }

    private function slug(string $value, string $context): string
    {
        $value = trim($value);

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "{$context} must use lowercase letters, numbers, and hyphens."
            );
        }

        return $value;
    }

    private function requiredString(mixed $value, string $context): string
    {
        $value = $this->optionalString($value, $context);

        if ($value === null) {
            throw new InvalidArgumentException(
                "{$context} must be a non-blank string."
            );
        }

        return $value;
    }

    private function optionalString(mixed $value, string $context): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "{$context} must be null or a string."
            );
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<mixed> $values
     * @param list<string> $allowedKeys
     */
    private function assertOnlyKeys(
        array $values,
        array $allowedKeys,
        string $context,
    ): void {
        $unknown = array_values(array_diff(
            array_map(
                static fn (mixed $key): string => (string) $key,
                array_keys($values),
            ),
            $allowedKeys,
        ));

        sort($unknown);

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                '%s contains unsupported key(s): %s.',
                $context,
                implode(', ', $unknown),
            ));
        }
    }
}