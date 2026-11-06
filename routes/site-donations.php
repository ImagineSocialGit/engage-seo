<?php

use App\Features\Donations\Http\Controllers\SiteDonationController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::post('/_site-donations/checkout', [SiteDonationController::class, 'store'])
        ->middleware('throttle:8,1')
        ->name('site.donations.checkout');

    Route::get('/_site-donations/return/{token}', [SiteDonationController::class, 'returned'])
        ->middleware('throttle:30,1')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('site.donations.return');
});