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
     * @return list<string>
     */
    public function themes(): array
    {
        $themes = config('sections.themes', []);

        if (! is_array($themes) || ! array_is_list($themes)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $theme): string => is_string($theme)
                    ? trim($theme)
                    : '',
                $themes,
            ),
            static fn (string $theme): bool => $theme !== '',
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function available(): array
    {
        $available = config('sections.available', []);

        return is_array($available)
            ? $available
            : [];
    }

    public function registered(string $component): bool
    {
        try {
            $this->definitionFor($component);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function supportsTheme(
        string $component,
        ?string $theme,
    ): bool {
        $this->definitionFor($component);

        if ($theme === null) {
            return true;
        }

        return in_array($theme, $this->themes(), true);
    }

    public function supportsLayout(
        string $component,
        ?string $layout,
    ): bool {
        $definition = $this->definitionFor($component);

        if ($layout === null) {
            return true;
        }

        return in_array($layout, $definition['layouts'], true);
    }

    public function viewFor(string $component): string
    {
        $component = trim($component);
        $definition = $this->definitionFor($component);
        $platformView = $definition['view'];

        if (! $this->views->exists($platformView)) {
            throw new RuntimeException(
                "Engage SEO section component [{$component}] references missing view [{$platformView}]."
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

        return $platformView;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];
        $configuredThemes = config('sections.themes', []);

        if (! is_array($configuredThemes) || ! array_is_list($configuredThemes)) {
            $errors[] = 'Engage SEO section themes must be a list.';
        } else {
            $themes = $this->themes();

            if ($themes === [] || count($themes) !== count($configuredThemes)) {
                $errors[] = 'Engage SEO section themes must contain only non-blank strings.';
            } else {
                foreach ($themes as $theme) {
                    if (! $this->validKey($theme)) {
                        $errors[] = "Invalid Engage SEO section theme: {$theme}.";
                    }
                }

                if (! in_array('default', $themes, true)) {
                    $errors[] = 'Engage SEO section themes must include [default].';
                }
            }
        }

        $configured = config('sections.available', []);

        if (! is_array($configured)) {
            $errors[] = 'Engage SEO section registry must be an array.';

            return array_values(array_unique($errors));
        }

        foreach ($configured as $component => $definition) {
            if (! is_string($component) || ! $this->validKey($component)) {
                $errors[] = 'Engage SEO section registry contains an invalid component key.';

                continue;
            }

            try {
                $this->definitionFor($component);
                $this->viewFor($component);
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array{view: string, layouts: list<string>}
     */
    private function definitionFor(string $component): array
    {
        $component = trim($component);

        if (! $this->validKey($component)) {
            throw new InvalidArgumentException(
                "Invalid Engage SEO section component: {$component}"
            );
        }

        $definition = $this->available()[$component] ?? null;

        if (! is_array($definition)) {
            throw new InvalidArgumentException(
                "Unknown Engage SEO section component: {$component}"
            );
        }

        $unknownKeys = array_values(array_diff(
            array_keys($definition),
            ['view', 'layouts'],
        ));

        if ($unknownKeys !== []) {
            sort($unknownKeys);

            throw new InvalidArgumentException(
                "Engage SEO section component [{$component}] contains unsupported definition key(s): "
                .implode(', ', $unknownKeys).'.'
            );
        }

        $view = $definition['view'] ?? null;

        if (! is_string($view) || trim($view) === '') {
            throw new InvalidArgumentException(
                "Engage SEO section component [{$component}] is missing a valid [view]."
            );
        }

        $layouts = $definition['layouts'] ?? null;

        if (! is_array($layouts) || ! array_is_list($layouts) || $layouts === []) {
            throw new InvalidArgumentException(
                "Engage SEO section component [{$component}] [layouts] must be a non-empty list."
            );
        }

        $normalizedLayouts = [];

        foreach ($layouts as $layout) {
            if (! is_string($layout) || ! $this->validKey(trim($layout))) {
                throw new InvalidArgumentException(
                    "Engage SEO section component [{$component}] contains an invalid layout."
                );
            }

            $normalizedLayouts[] = trim($layout);
        }

        $normalizedLayouts = array_values(array_unique($normalizedLayouts));

        if (! in_array('default', $normalizedLayouts, true)) {
            throw new InvalidArgumentException(
                "Engage SEO section component [{$component}] layouts must include [default]."
            );
        }

        return [
            'view' => trim($view),
            'layouts' => $normalizedLayouts,
        ];
    }

    private function validKey(string $value): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $value) === 1;
    }
}