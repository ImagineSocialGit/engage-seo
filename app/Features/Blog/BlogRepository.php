<?php

namespace App\Features\Blog;

use App\Features\Blog\Models\BlogCategory;
use App\Features\Blog\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class BlogRepository
{
    public function findPublishedPost(string $slug): ?BlogPost
    {
        return $this->publishedPostsQuery()
            ->with('categories')
            ->where('slug', $slug)
            ->first();
    }

    public function findCategory(string $slug): ?BlogCategory
    {
        return BlogCategory::query()
            ->where('slug', $slug)
            ->first();
    }

    public function postsPage(
        int $perPage,
        ?BlogCategory $category = null,
    ): LengthAwarePaginator {
        $query = $this->publishedPostsQuery()
            ->with('categories');

        if ($category !== null) {
            $query->whereHas(
                'categories',
                static fn (Builder $categoryQuery): Builder => $categoryQuery
                    ->whereKey($category->getKey()),
            );
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function featuredPosts(int $limit): Collection
    {
        if ($limit < 1) {
            return new Collection();
        }

        return $this->publishedPostsQuery()
            ->with('categories')
            ->where('featured', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, BlogCategory>
     */
    public function publishedCategories(): Collection
    {
        return BlogCategory::query()
            ->whereHas(
                'posts',
                static fn (Builder $query): Builder => $query->published(),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function publishedPostsForSitemap(): Collection
    {
        return $this->publishedPostsQuery()
            ->where('indexable', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, BlogCategory>
     */
    public function categoriesForSitemap(): Collection
    {
        return BlogCategory::query()
            ->where('indexable', true)
            ->whereHas(
                'posts',
                static fn (Builder $query): Builder => $query
                    ->published()
                    ->where('indexable', true),
            )
            ->orderBy('id')
            ->get();
    }

    private function publishedPostsQuery(): Builder
    {
        return BlogPost::query()->published();
    }
}