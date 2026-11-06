<?php

namespace App\Features\Donations;

use Illuminate\Support\ServiceProvider;

final class DonationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../../routes/site-donations.php');
    }
}