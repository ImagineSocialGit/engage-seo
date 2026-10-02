<?php

namespace App\Features\Blog;

use App\Contracts\Clients\ClientBootstrapContributor;
use App\Features\Blog\Models\BlogCategory;
use App\Features\Blog\Models\BlogPost;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BlogClientBootstrapContributor implements ClientBootstrapContributor
{
    public function __construct(
        private readonly BlogEditorialPromotionContributor $editorial,
    ) {
    }

    public function key(): string
    {
        return 'blog';
    }

    public function validationErrors(array $payload): array
    {
        $errors = $this->editorial->validationErrors($payload);

        if ($errors !== []) {
            return $errors;
        }

        if (BlogCategory::query()->exists()
            || BlogPost::query()->exists()
            || DB::table('blog_category_post')->exists()
        ) {
            $errors[] = 'Blog client bootstrap may initialize only empty Blog editorial tables. Existing Blog state must be changed through editorial promotion or an explicit data migration.';
        }

        return $errors;
    }

    public function apply(array $payload): void
    {
        $errors = $this->validationErrors($payload);

        if ($errors !== []) {
            throw new InvalidArgumentException(
                "Blog client bootstrap is invalid:\n- "
                .implode("\n- ", $errors)
            );
        }

        $this->editorial->applySection($payload);
    }
}