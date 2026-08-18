<?php

namespace App\Support\Sections;

use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SectionManager
{
    public function __construct(
        private readonly ViewFactory $views,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function available(): array
    {
        $available = config('sections.available', []);

        if (! is_array($available)) {
            return [];
        }

        return array_filter(
            $available,
            fn (mixed $view, mixed $key): bool => is_string($key)
                && trim($key) !== ''
                && is_string($view)
                && trim($view) !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function registered(string $component): bool
    {
        return array_key_exists(
            trim($component),
            $this->available()
        );
    }

    public function viewFor(string $component): string
    {
        $component = trim($component);

        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $component)) {
            throw new InvalidArgumentException(
                "Invalid Engage SEO section component: {$component}"
            );
        }

        $clientView = 'client::sections.'.str_replace('_', '-', $component);

        try {
            if ($this->views->exists($clientView)) {
                return $clientView;
            }
        } catch (Throwable) {
            // No selected-client namespace is registered.
        }

        $view = $this->available()[$component] ?? null;

        if (! is_string($view) || trim($view) === '') {
            throw new InvalidArgumentException(
                "Unknown Engage SEO section component: {$component}"
            );
        }

        if (! $this->views->exists($view)) {
            throw new RuntimeException(
                "Engage SEO section component [{$component}] references missing view [{$view}]."
            );
        }

        return $view;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];

        foreach ($this->available() as $component => $view) {
            try {
                $this->viewFor($component);
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return array_values(array_unique($errors));
    }
}