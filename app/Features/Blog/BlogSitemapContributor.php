<?php

namespace App\Features\Blog;

use App\Contracts\Seo\SitemapContributor;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class BlogSitemapContributor implements SitemapContributor
{
    public function __construct(
        private readonly BlogConfiguration $configuration,
        private readonly BlogRepository $repository,
    ) {
    }

    public function sitemapEntries(): array
    {
        if (! $this->schemaReady()) {
            return [];
        }

        $entries = [];
        $index = $this->configuration->index();

        if ($index['indexable']) {
            $entries[] = [
                'loc' => $this->absoluteUrl(
                    $this->configuration->basePath()
                ),
            ];
        }

        if ($this->configuration->categoryIndexable()) {
            foreach ($this->repository->categoriesForSitemap() as $category) {
                $entries[] = [
                    'loc' => $this->absoluteUrl(
                        $this->configuration->categoryPath($category->slug)
                    ),
                ];
            }
        }

        foreach ($this->repository->publishedPostsForSitemap() as $post) {
            $entries[] = [
                'loc' => $this->absoluteUrl(
                    $this->configuration->articlePath($post->slug)
                ),
            ];
        }

        return $entries;
    }

    private function schemaReady(): bool
    {
        foreach ([
            'blog_categories',
            'blog_posts',
            'blog_category_post',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function absoluteUrl(string $path): string
    {
        $baseUrl = config('app.url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new RuntimeException(
                'Application URL must be configured before resolving Blog sitemap URLs.'
            );
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}