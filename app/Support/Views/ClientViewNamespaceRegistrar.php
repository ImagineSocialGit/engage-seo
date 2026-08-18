<?php

namespace App\Support\Views;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Application;

final class ClientViewNamespaceRegistrar
{
    public function __construct(
        private readonly Application $app,
        private readonly ViewFactory $views,
    ) {
    }

    public function register(): void
    {
        $clientKey = config('client.key');

        if (! is_string($clientKey) || trim($clientKey) === '') {
            return;
        }

        $viewPath = $this->app->basePath(
            'clients/'.trim($clientKey).'/resources/views'
        );

        if (! is_dir($viewPath)) {
            return;
        }

        $this->views->addNamespace('client', $viewPath);
    }
}