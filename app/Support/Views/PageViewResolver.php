<?php

namespace App\Support\Views;

use Illuminate\Contracts\View\Factory as ViewFactory;
use RuntimeException;
use Throwable;

final class PageViewResolver
{
    public function __construct(
        private readonly ViewFactory $views,
    ) {
    }

    public function resolve(): string
    {
        try {
            if ($this->views->exists('client::pages.public')) {
                return 'client::pages.public';
            }
        } catch (Throwable) {
            // No selected-client namespace is registered.
        }

        if (! $this->views->exists('pages.public')) {
            throw new RuntimeException(
                'Engage SEO public page view [pages.public] does not exist.'
            );
        }

        return 'pages.public';
    }
}