<?php

namespace App\Features\Blog;

use App\Features\Blog\Models\BlogCategory;
use App\Features\Blog\Models\BlogPost;
use App\Support\Media\MediaAssetResolver;
use App\Support\Pages\PageMetaResolver;
use InvalidArgumentException;

final class BlogMetaResolver
{
    public function __construct(
        private readonly BlogConfiguration $configuration,
        private readonly PageMetaResolver $pages,
        private readonly MediaAssetResolver $media,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $index = $this->configuration->index();
        $path = $this->configuration->basePath();

        return $this->pages->resolve([
            'title' => $index['meta_title'] ?? $index['title'],
            'description' => $index['meta_description'],
            'indexable' => $index['indexable'],
            'structured_data' => [[
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $index['title'],
                'url' => $this->absoluteUrl($path),
            ]],
        ], $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function category(BlogCategory $category): array
    {
        $path = $this->configuration->categoryPath($category->slug);

        return $this->pages->resolve([
            'title' => $category->meta_title ?: $category->name,
            'description' => $category->meta_description ?: $category->description,
            'indexable' => $this->configuration->categoryIndexable()
                && (bool) $category->indexable,
            'structured_data' => [[
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $category->name,
                'url' => $this->absoluteUrl($path),
            ]],
        ], $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function post(BlogPost $post): array
    {
        $path = $this->configuration->articlePath($post->slug);
        $image = $this->imageUrl($post->featured_image_asset);
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $this->absoluteUrl($path),
            ],
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString()
                ?? $post->published_at?->toAtomString(),
            'publisher' => [
                '@type' => 'Organization',
                'name' => $this->siteName(),
            ],
        ];

        if (is_string($post->excerpt) && trim($post->excerpt) !== '') {
            $node['description'] = trim($post->excerpt);
        }

        if (is_string($post->author_name) && trim($post->author_name) !== '') {
            $node['author'] = [
                '@type' => 'Person',
                'name' => trim($post->author_name),
            ];
        }

        if ($image !== null) {
            $node['image'] = $image;
        }

        return $this->pages->resolve([
            'title' => $post->meta_title ?: $post->title,
            'description' => $post->meta_description ?: $post->excerpt,
            'image' => $image,
            'indexable' => (bool) $post->indexable,
            'open_graph' => [
                'type' => 'article',
            ],
            'structured_data' => [$node],
        ], $path);
    }

    private function imageUrl(mixed $asset): ?string
    {
        if (! is_string($asset) || trim($asset) === '') {
            return null;
        }

        $resolved = $this->media->resolve(trim($asset));
        $url = $resolved['fallback']['url'] ?? null;

        if (! is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException(
                'Blog featured image did not resolve to a public fallback URL.'
            );
        }

        return $this->absoluteUrl($url);
    }

    private function absoluteUrl(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
            return $pathOrUrl;
        }

        $baseUrl = config('app.url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new InvalidArgumentException(
                'Application URL must be configured before resolving Blog URLs.'
            );
        }

        return rtrim($baseUrl, '/').'/'.ltrim($pathOrUrl, '/');
    }

    private function siteName(): string
    {
        foreach ([
            config('site.name'),
            config('client.name'),
            config('app.name'),
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        throw new InvalidArgumentException(
            'Blog structured data requires a site, client, or application name.'
        );
    }
}