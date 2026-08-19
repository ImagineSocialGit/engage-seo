<?php

namespace App\Features\Blog;

use App\Contracts\SetupValidation\SetupValidationContributor;
use App\Support\Seo\RedirectRepository;
use InvalidArgumentException;

final class BlogSetupValidator implements SetupValidationContributor
{
    public function __construct(
        private readonly BlogConfiguration $configuration,
        private readonly RedirectRepository $redirects,
    ) {
    }

    public function validationErrors(?string $basePath = null): array
    {
        $errors = $this->configuration->validationErrors();

        if ($errors !== []) {
            return $errors;
        }

        $blogPath = $this->configuration->basePath();
        $pages = config('pages', []);

        if (is_array($pages)) {
            foreach ($pages as $pageKey => $page) {
                if (! is_string($pageKey) || ! is_array($page)) {
                    continue;
                }

                $path = $page['path'] ?? null;

                if (! is_string($path) || trim($path) === '') {
                    continue;
                }

                try {
                    $path = $this->normalizePath($path);
                } catch (InvalidArgumentException) {
                    continue;
                }

                if ($this->routeOwnsPath($blogPath, $path)) {
                    $errors[] = "Configured page [{$pageKey}] path [{$path}] conflicts with Blog Feature route space [{$blogPath}].";
                }
            }
        }

        if ($this->redirects->validationErrors() === []) {
            foreach ($this->redirects->all() as $redirect) {
                $from = $redirect['from'];

                if ($this->routeOwnsPath($blogPath, $from)) {
                    $errors[] = "SEO redirect source [{$from}] conflicts with Blog Feature route space [{$blogPath}].";
                }
            }
        }

        return array_values(array_unique($errors));
    }

    private function routeOwnsPath(string $basePath, string $path): bool
    {
        if ($path === $basePath) {
            return true;
        }

        if (! str_starts_with($path, $basePath.'/')) {
            return false;
        }

        $relative = trim(substr($path, strlen($basePath)), '/');
        $segments = $relative === '' ? [] : explode('/', $relative);

        if (count($segments) === 1) {
            return true;
        }

        return count($segments) === 2
            && $segments[0] === 'category';
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === ''
            || ! str_starts_with($path, '/')
            || str_contains($path, '?')
            || str_contains($path, '#')
        ) {
            throw new InvalidArgumentException('Invalid page path.');
        }

        return $path === '/'
            ? '/'
            : '/'.trim($path, '/');
    }
}