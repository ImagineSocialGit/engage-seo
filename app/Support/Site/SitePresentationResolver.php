<?php

namespace App\Support\Site;

use InvalidArgumentException;

final class SitePresentationResolver
{
    /**
     * @var array<string, array<string, string>>
     */
    private const THEME_VARIABLES = [
        'colors' => [
            'background' => '--site-color-background',
            'surface' => '--site-color-surface',
            'text' => '--site-color-text',
            'muted' => '--site-color-muted',
            'primary' => '--site-color-primary',
            'primary_contrast' => '--site-color-primary-contrast',
            'border' => '--site-color-border',
            'focus' => '--site-color-focus',
            'inverse_background' => '--site-color-inverse-background',
            'inverse_surface' => '--site-color-inverse-surface',
            'inverse_text' => '--site-color-inverse-text',
            'inverse_muted' => '--site-color-inverse-muted',
            'inverse_border' => '--site-color-inverse-border',
        ],
        'typography' => [
            'body_font_family' => '--site-font-body',
            'heading_font_family' => '--site-font-heading',
        ],
        'layout' => [
            'content_max_width' => '--site-content-max-width',
        ],
        'radius' => [
            'control' => '--site-radius-control',
            'surface' => '--site-radius-surface',
        ],
    ];

    public function __construct(
        private readonly SiteLinkNormalizer $links,
        private readonly BusinessPresentationResolver $business,
        private readonly SiteShellResolver $shell,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(?string $currentPath = null): array
    {
        $currentPath = $this->links->normalizePath($currentPath ?? '/');

        return [
            ...$this->identity(),
            'business' => $this->business->resolve($currentPath),
            'shell' => $this->shell->resolve($currentPath),
            'theme' => $this->theme(),
        ];
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];

        foreach ([
            fn (): array => $this->identity(),
            fn (): array => $this->business->resolve('/'),
            fn (): array => $this->shell->resolve('/'),
            fn (): array => $this->theme(),
        ] as $check) {
            try {
                $check();
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(): array
    {
        $siteName = $this->optionalString(
            config('site.name'),
            'site.name',
        );
        $clientName = $this->optionalString(
            config('client.name'),
            'client.name',
        );
        $applicationName = $this->optionalString(
            config('app.name'),
            'app.name',
        );
        $name = $siteName ?? $clientName ?? $applicationName;

        if ($name === null) {
            throw new InvalidArgumentException(
                'Site presentation requires a non-blank site, client, or application name.'
            );
        }

        $brand = config('site.brand', []);

        if (! is_array($brand)) {
            throw new InvalidArgumentException(
                'Site configuration [brand] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $brand,
            ['logo', 'logo_alt'],
            'site.brand',
        );

        $logo = $this->optionalString(
            $brand['logo'] ?? null,
            'site.brand.logo',
        );

        if ($logo !== null) {
            $this->links->assertHttpOrPathUrl(
                $logo,
                'site.brand.logo',
            );
        }

        $logoAlt = $this->optionalString(
            $brand['logo_alt'] ?? null,
            'site.brand.logo_alt',
        ) ?? $name;

        return [
            'name' => $name,
            'brand' => [
                'logo' => $logo,
                'logo_alt' => $logoAlt,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function theme(): array
    {
        $theme = config('site.theme', []);

        if (! is_array($theme)) {
            throw new InvalidArgumentException(
                'Site configuration [theme] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $theme,
            array_keys(self::THEME_VARIABLES),
            'site.theme',
        );

        $resolved = [];
        $cssVariables = [];

        foreach (self::THEME_VARIABLES as $group => $tokens) {
            $values = $theme[$group] ?? null;

            if (! is_array($values)) {
                throw new InvalidArgumentException(
                    "Site configuration [theme.{$group}] must be an array."
                );
            }

            $this->assertOnlyKeys(
                $values,
                array_keys($tokens),
                "site.theme.{$group}",
            );

            $resolved[$group] = [];

            foreach ($tokens as $token => $cssVariable) {
                if (! array_key_exists($token, $values)) {
                    throw new InvalidArgumentException(
                        "Site theme token [{$group}.{$token}] is missing."
                    );
                }

                $value = $this->cssValue(
                    $values[$token],
                    "site.theme.{$group}.{$token}",
                );

                $resolved[$group][$token] = $value;
                $cssVariables[$cssVariable] = $value;
            }
        }

        return [
            ...$resolved,
            'css_variables' => $cssVariables,
        ];
    }

    private function optionalString(mixed $value, string $context): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be null or a string."
            );
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function cssValue(mixed $value, string $context): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "Site theme token [{$context}] must be a non-blank string."
            );
        }

        $value = trim($value);

        if (preg_match('/[{};<>\\r\\n]/', $value)) {
            throw new InvalidArgumentException(
                "Site theme token [{$context}] contains unsupported CSS characters."
            );
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     * @param list<string> $allowedKeys
     */
    private function assertOnlyKeys(
        array $values,
        array $allowedKeys,
        string $context,
    ): void {
        $unknown = array_values(array_diff(
            array_map(
                static fn (mixed $key): string => (string) $key,
                array_keys($values),
            ),
            $allowedKeys,
        ));

        sort($unknown);

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Site configuration [%s] contains unsupported key(s): %s.',
                $context,
                implode(', ', $unknown),
            ));
        }
    }
}