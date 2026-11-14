<?php

use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Seo\ClientIconController;
use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Seo\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/site-icons/{filename}', ClientIconController::class)
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('site.icons');

Route::get('/robots.txt', RobotsController::class)
    ->name('seo.robots');

Route::get('/sitemap.xml', SitemapController::class)
    ->name('seo.sitemap');

Route::get('/', PublicPageController::class)
    ->name('pages.home');

Route::fallback(PublicPageController::class)
    ->name('pages.fallback');