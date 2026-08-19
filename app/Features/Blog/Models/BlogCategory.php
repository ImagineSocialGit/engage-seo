<?php

namespace App\Features\Blog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'meta_title',
    'meta_description',
    'indexable',
    'sort_order',
])]
final class BlogCategory extends Model
{
    protected function casts(): array
    {
        return [
            'indexable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'blog_category_post',
        )->withTimestamps();
    }
}