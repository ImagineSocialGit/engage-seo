<?php

namespace App\Support\Seo;

use App\Contracts\Seo\StructuredDataContributor;
use InvalidArgumentException;
use RuntimeException;

final class PagePersonStructuredDataContributor implements StructuredDataContributor
{
    public function structuredData(array $page, array $site): array
    {
        $personKey = $page['person'] ?? null;

        if ($personKey === null) {
            return [];
        }

        if (! is_string($personKey) || trim($personKey) === '') {
            throw new RuntimeException(
                'Resolved page [person] must be null or a non-blank person key.'
            );
        }

        $people = $site['people'] ?? [];

        if (! is_array($people)) {
            throw new RuntimeException(
                'Resolved site [people] must be an array.'
            );
        }

        $person = $people[trim($personKey)] ?? null;

        if (! is_array($person)) {
            throw new RuntimeException(
                "Resolved page references unknown site person [{$personKey}]."
            );
        }

        $url = $person['url'] ?? $page['path'] ?? null;

        if (! is_string($url) || trim($url) === '') {
            throw new RuntimeException(
                "Site person [{$personKey}] requires a URL for page-level Person structured data."
            );
        }

        $absoluteUrl = $this->absoluteUrl($url);

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => $absoluteUrl.'#person',
            'name' => $person['name'],
            'url' => $absoluteUrl,
            'worksFor' => [
                '@id' => $this->baseUrl().'/#organization',
            ],
        ];

        if (is_string($person['job_title'] ?? null)) {
            $node['jobTitle'] = $person['job_title'];
        }

        if (is_string($person['description'] ?? null)) {
            $node['description'] = $person['description'];
        }

        if (is_array($person['same_as'] ?? null)
            && $person['same_as'] !== []
        ) {
            $node['sameAs'] = array_values($person['same_as']);
        }

        return [$node];
    }

    private function absoluteUrl(string $value): string
    {
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        return $this->baseUrl().'/'.ltrim($value, '/');
    }

    private function baseUrl(): string
    {
        $url = config('app.url');

        if (! is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException(
                'Application URL must be configured before resolving Person structured data.'
            );
        }

        return rtrim(trim($url), '/');
    }
}