<?php

namespace App\Support\Seo;

use App\Contracts\Seo\StructuredDataContributor;
use InvalidArgumentException;
use RuntimeException;

final class PageBreadcrumbStructuredDataContributor implements StructuredDataContributor
{
    public function structuredData(array $page, array $site): array
    {
        $breadcrumbs = $page['breadcrumbs'] ?? [];

        if ($breadcrumbs === []) {
            return [];
        }

        if (! is_array($breadcrumbs) || ! array_is_list($breadcrumbs)) {
            throw new RuntimeException(
                'Resolved page [breadcrumbs] must be a list.'
            );
        }

        $items = [];

        foreach ($breadcrumbs as $index => $breadcrumb) {
            if (! is_array($breadcrumb)) {
                throw new RuntimeException(
                    "Resolved breadcrumb [{$index}] must be an array."
                );
            }

            $label = $breadcrumb['label'] ?? null;
            $url = $breadcrumb['url'] ?? null;

            if (! is_string($label) || trim($label) === ''
                || ! is_string($url) || trim($url) === ''
            ) {
                throw new RuntimeException(
                    "Resolved breadcrumb [{$index}] must contain a label and URL."
                );
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => trim($label),
                'item' => $this->absoluteUrl(trim($url)),
            ];
        }

        return [[
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]];
    }

    private function absoluteUrl(string $value): string
    {
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        $baseUrl = config('app.url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new InvalidArgumentException(
                'Application URL must be configured before resolving BreadcrumbList structured data.'
            );
        }

        return rtrim(trim($baseUrl), '/').'/'.ltrim($value, '/');
    }
}