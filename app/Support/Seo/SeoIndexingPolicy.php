<?php

namespace App\Support\Seo;

use InvalidArgumentException;

final class SeoIndexingPolicy
{
    public function siteIndexable(): bool
    {
        $enabled = config('site.seo.indexing_enabled', false);

        if (! is_bool($enabled)) {
            throw new InvalidArgumentException(
                'Site SEO [indexing_enabled] must be a boolean.'
            );
        }

        $clientKey = config('client.key');

        return $enabled
            && is_string($clientKey)
            && trim($clientKey) !== ''
            && config('app.env') === 'production';
    }

    public function pageIndexable(bool $requested): bool
    {
        return $requested && $this->siteIndexable();
    }

    public function sitemapEnabled(): bool
    {
        $enabled = config('site.seo.sitemap_enabled', true);

        if (! is_bool($enabled)) {
            throw new InvalidArgumentException(
                'Site SEO [sitemap_enabled] must be a boolean.'
            );
        }

        return $enabled && $this->siteIndexable();
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];

        foreach ([
            'indexing_enabled' => false,
            'sitemap_enabled' => true,
            'default_indexable' => true,
        ] as $key => $default) {
            $value = config("site.seo.{$key}", $default);

            if (! is_bool($value)) {
                $errors[] = "Site SEO [{$key}] must be a boolean.";
            }
        }

        return $errors;
    }
}