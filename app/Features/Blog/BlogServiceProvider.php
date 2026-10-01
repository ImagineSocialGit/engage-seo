<?php

namespace App\Features\Blog;

use App\Features\Blog\Http\Controllers\BlogController;
use App\Features\Blog\Http\Controllers\BlogLegacyRedirectController;
use App\Support\Editorial\EditorialPromotionRegistry;
use App\Support\Seo\SeoExtensionRegistry;
use App\Support\SetupValidation\SetupValidationRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/features/blog.php'),
            'features.blog',
        );

        $this->app->singleton(BlogConfiguration::class);
        $this->app->singleton(BlogContentNormalizer::class);
        $this->app->singleton(BlogAuthorResolver::class);
        $this->app->singleton(BlogRepository::class);
        $this->app->singleton(BlogPresenter::class);
        $this->app->singleton(BlogMetaResolver::class);
        $this->app->singleton(BlogSitemapContributor::class);
        $this->app->singleton(BlogSetupValidator::class);
        $this->app->singleton(BlogViewResolver::class);
        $this->app->singleton(BlogEditorialPromotionContributor::class);
    }

    public function boot(
        SetupValidationRegistry $setupValidation,
        SeoExtensionRegistry $seo,
        EditorialPromotionRegistry $editorial,
    ): void {
        $this->loadMigrationsFrom(
            __DIR__.'/database/migrations'
        );

        $setupValidation->register(BlogSetupValidator::class);
        $seo->registerSitemapContributor(
            BlogSitemapContributor::class
        );
        $editorial->register(
            BlogEditorialPromotionContributor::class
        );

        try {
            $configuration = $this->app->make(BlogConfiguration::class);
            $path = $configuration->basePath();
            $legacyPaths = $configuration->legacyPaths();
        } catch (InvalidArgumentException) {
            return;
        }

        Route::middleware('web')->group(function () use ($path, $legacyPaths): void {
            foreach ($legacyPaths as $legacyPath) {
                Route::get(
                    $legacyPath,
                    [BlogLegacyRedirectController::class, 'index'],
                );

                Route::get(
                    $legacyPath.'/category/{categorySlug}',
                    [BlogLegacyRedirectController::class, 'category'],
                );

                Route::get(
                    $legacyPath.'/{postSlug}',
                    [BlogLegacyRedirectController::class, 'show'],
                );
            }

            Route::get(
                $path,
                [BlogController::class, 'index'],
            )->name('blog.index');

            Route::get(
                $path.'/category/{categorySlug}',
                [BlogController::class, 'category'],
            )->name('blog.category');

            Route::get(
                $path.'/{postSlug}',
                [BlogController::class, 'show'],
            )->name('blog.show');
        });
    }
}