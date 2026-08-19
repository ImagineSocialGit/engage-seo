<?php

namespace App\Features\Blog;

use App\Features\Blog\Models\BlogCategory;
use App\Features\Blog\Models\BlogPost;
use InvalidArgumentException;

final class BlogPresenter
{
    public function __construct(
        private readonly BlogConfiguration $configuration,
        private readonly BlogContentNormalizer $content,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function post(BlogPost $post, bool $withContent = false): array
    {
        $image = null;

        if ($post->featured_image_asset !== null) {
            if (! is_string($post->featured_image_alt)) {
                throw new InvalidArgumentException(
                    "Blog post [{$post->slug}] featured image requires an explicit alt string."
                );
            }

            $image = [
                'asset' => $post->featured_image_asset,
                'alt' => $post->featured_image_alt,
                'sizes' => '(min-width: 48rem) 33vw, 100vw',
                'loading' => 'lazy',
                'fetchpriority' => 'auto',
            ];
        }

        return [
            'id' => $post->getKey(),
            'slug' => $post->slug,
            'path' => $this->configuration->articlePath($post->slug),
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'author_name' => $post->author_name,
            'featured' => (bool) $post->featured,
            'image' => $image,
            'published_at' => $post->published_at,
            'updated_at' => $post->updated_at,
            'categories' => $post->categories
                ->map(fn (BlogCategory $category): array => $this->category($category))
                ->values()
                ->all(),
            'content' => $withContent
                ? $this->content->normalize($post->content)
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function category(BlogCategory $category): array
    {
        return [
            'id' => $category->getKey(),
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,
            'path' => $this->configuration->categoryPath($category->slug),
        ];
    }
}