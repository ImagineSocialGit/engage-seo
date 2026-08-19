<?php

namespace App\Features\Blog\Http\Controllers;

use App\Features\Blog\BlogConfiguration;
use App\Features\Blog\BlogMetaResolver;
use App\Features\Blog\BlogPresenter;
use App\Features\Blog\BlogRepository;
use App\Features\Blog\BlogViewResolver;
use App\Http\Controllers\Controller;
use App\Support\Site\SitePresentationResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BlogController extends Controller
{
    public function index(
        Request $request,
        BlogConfiguration $configuration,
        BlogRepository $repository,
        BlogPresenter $presenter,
        BlogMetaResolver $meta,
        BlogViewResolver $views,
        SitePresentationResolver $sitePresentation,
    ): View {
        $path = $configuration->basePath();
        $index = $configuration->index();
        $posts = $repository->postsPage(
            $configuration->postsPerPage(),
        );

        $posts->through(
            fn ($post): array => $presenter->post($post)
        );

        return view($views->index(), [
            'meta' => $meta->index(),
            'site' => $sitePresentation->resolve($path),
            'blog' => $index,
            'featured' => $repository->featuredPosts(
                $index['featured_limit'],
            )->map(
                fn ($post): array => $presenter->post($post)
            )->values()->all(),
            'categories' => $repository->publishedCategories()
                ->map(
                    fn ($category): array => $presenter->category($category)
                )->values()->all(),
            'posts' => $posts,
        ]);
    }

    public function category(
        string $categorySlug,
        BlogConfiguration $configuration,
        BlogRepository $repository,
        BlogPresenter $presenter,
        BlogMetaResolver $meta,
        BlogViewResolver $views,
        SitePresentationResolver $sitePresentation,
    ): View {
        $category = $repository->findCategory($categorySlug);

        abort_if($category === null, 404);

        $path = $configuration->categoryPath($category->slug);
        $posts = $repository->postsPage(
            $configuration->postsPerPage(),
            $category,
        );

        $posts->through(
            fn ($post): array => $presenter->post($post)
        );

        return view($views->category(), [
            'meta' => $meta->category($category),
            'site' => $sitePresentation->resolve($path),
            'blog' => $configuration->index(),
            'blogPath' => $configuration->basePath(),
            'category' => $presenter->category($category),
            'posts' => $posts,
        ]);
    }

    public function show(
        string $postSlug,
        BlogConfiguration $configuration,
        BlogRepository $repository,
        BlogPresenter $presenter,
        BlogMetaResolver $meta,
        BlogViewResolver $views,
        SitePresentationResolver $sitePresentation,
    ): View {
        $post = $repository->findPublishedPost($postSlug);

        abort_if($post === null, 404);

        $path = $configuration->articlePath($post->slug);

        return view($views->show(), [
            'meta' => $meta->post($post),
            'site' => $sitePresentation->resolve($path),
            'blog' => $configuration->index(),
            'blogPath' => $configuration->basePath(),
            'post' => $presenter->post($post, true),
        ]);
    }
}