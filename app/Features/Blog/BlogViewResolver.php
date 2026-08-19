<?php

namespace App\Features\Blog;

use Illuminate\Contracts\View\Factory as ViewFactory;
use RuntimeException;
use Throwable;

final class BlogViewResolver
{
    public function __construct(
        private readonly ViewFactory $views,
    ) {
    }

    public function index(): string
    {
        return $this->resolve('index');
    }

    public function category(): string
    {
        return $this->resolve('category');
    }

    public function show(): string
    {
        return $this->resolve('show');
    }

    private function resolve(string $view): string
    {
        $clientView = "client::features.blog.{$view}";

        try {
            if ($this->views->exists($clientView)) {
                return $clientView;
            }
        } catch (Throwable) {
            // No selected-client namespace is registered.
        }

        $platformView = "features.blog.{$view}";

        if (! $this->views->exists($platformView)) {
            throw new RuntimeException(
                "Blog Feature view [{$platformView}] does not exist."
            );
        }

        return $platformView;
    }
}