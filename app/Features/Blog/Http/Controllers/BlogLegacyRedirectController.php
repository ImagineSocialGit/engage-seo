<?php

namespace App\Features\Blog\Http\Controllers;

use App\Features\Blog\BlogConfiguration;
use App\Features\Blog\BlogRepository;
use Illuminate\Http\RedirectResponse;

final class BlogLegacyRedirectController
{
    public function index(
        BlogConfiguration $configuration,
    ): RedirectResponse {
        return redirect()->to(
            $configuration->basePath(),
            301,
        );
    }

    public function category(
        string $categorySlug,
        BlogConfiguration $configuration,
        BlogRepository $repository,
    ): RedirectResponse {
        abort_if(
            $repository->findCategory($categorySlug) === null,
            404,
        );

        return redirect()->to(
            $configuration->categoryPath($categorySlug),
            301,
        );
    }

    public function show(
        string $postSlug,
        BlogConfiguration $configuration,
        BlogRepository $repository,
    ): RedirectResponse {
        abort_if(
            $repository->findPublishedPost($postSlug) === null,
            404,
        );

        return redirect()->to(
            $configuration->articlePath($postSlug),
            301,
        );
    }
}