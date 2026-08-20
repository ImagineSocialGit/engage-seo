<?php

namespace App\Features\Blog;

use App\Features\Blog\Http\Controllers\BlogController;
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
            $path = $this->app->make(BlogConfiguration::class)
                ->basePath();
        } catch (InvalidArgumentException) {
            return;
        }

        Route::middleware('web')->group(function () use ($path): void {
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