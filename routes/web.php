<?php

use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicPageController::class)
    ->name('pages.home');

Route::fallback(PublicPageController::class)
    ->name('pages.fallback');