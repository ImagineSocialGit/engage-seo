<?php

namespace App\Features\Blog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
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
])]
final class BlogPost extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'featured' => 'boolean',
            'indexable' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogCategory::class,
            'blog_category_post',
        )
            ->withTimestamps()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}