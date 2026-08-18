<?php

namespace App\Support\Pages;

use App\Support\Seo\SeoIndexingPolicy;
use InvalidArgumentException;

final class PageMetaResolver
{
    public function __construct(
        private readonly SeoIndexingPolicy $indexing,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function resolve(array $meta, string $path): array
    {
        $title = $this->stringValue($meta['title'] ?? null)
            ?? $this->stringValue(config('site.name'))
            ?? $this->stringValue(config('client.name'))
            ?? $this->stringValue(config('app.name'))
            ?? '';

        $description = $this->stringValue($meta['description'] ?? null)
            ?? $this->stringValue(config('site.seo.default_description'))
            ?? '';

        $canonical = $this->canonical(
            $meta['canonical'] ?? null,
            $path,
        );

        $requestedIndexable = $meta['indexable']
            ?? config('site.seo.default_indexable', true);

        if (! is_bool($requestedIndexable)) {
            throw new InvalidArgumentException(
                'Page meta [indexable] must be a boolean.'
            );
        }

        $indexable = $this->indexing->pageIndexable(
            $requestedIndexable
        );

        $image = $this->stringValue($meta['image'] ?? null)
            ?? $this->stringValue(config('site.seo.default_image'));

        $openGraph = $meta['open_graph'] ?? [];

        if (! is_array($openGraph)) {
            throw new InvalidArgumentException(
                'Page meta [open_graph] must be an array.'
            );
        }

        $twitter = $meta['twitter'] ?? [];

        if (! is_array($twitter)) {
            throw new InvalidArgumentException(
                'Page meta [twitter] must be an array.'
            );
        }

        $structuredData = $meta['structured_data'] ?? [];

        if (! is_array($structuredData) || ! array_is_list($structuredData)) {
            throw new InvalidArgumentException(
                'Page meta [structured_data] must be a list.'
            );
        }

        foreach ($structuredData as $index => $node) {
            if (! is_array($node) || array_is_list($node)) {
                throw new InvalidArgumentException(
                    "Page meta [structured_data] node [{$index}] must be an associative array."
                );
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'indexable' => $indexable,
            'robots' => $indexable
                ? 'index,follow'
                : 'noindex,nofollow',
            'open_graph' => [
                'type' => $this->stringValue($openGraph['type'] ?? null)
                    ?? $this->stringValue(config('site.seo.open_graph_type'))
                    ?? 'website',
                'title' => $this->stringValue($openGraph['title'] ?? null)
                    ?? $title,
                'description' => $this->stringValue($openGraph['description'] ?? null)
                    ?? $description,
                'url' => $this->stringValue($openGraph['url'] ?? null)
                    ?? $canonical,
                'image' => $this->stringValue($openGraph['image'] ?? null)
                    ?? $image,
            ],
            'twitter' => [
                'card' => $this->stringValue($twitter['card'] ?? null)
                    ?? $this->stringValue(config('site.seo.twitter_card'))
                    ?? 'summary_large_image',
                'title' => $this->stringValue($twitter['title'] ?? null)
                    ?? $title,
                'description' => $this->stringValue($twitter['description'] ?? null)
                    ?? $description,
                'image' => $this->stringValue($twitter['image'] ?? null)
                    ?? $image,
            ],
            'structured_data' => array_values($structuredData),
        ];
    }

    private function canonical(mixed $configured, string $path): string
    {
        $configured = $this->stringValue($configured);

        if ($configured !== null) {
            $scheme = parse_url($configured, PHP_URL_SCHEME);
            $host = parse_url($configured, PHP_URL_HOST);

            if (
                in_array($scheme, ['http', 'https'], true)
                && is_string($host)
                && trim($host) !== ''
            ) {
                return $configured;
            }

            if (! str_starts_with($configured, '/')) {
                throw new InvalidArgumentException(
                    'Page meta [canonical] must be an absolute http/https URL or an absolute path.'
                );
            }

            $path = $configured;
        }

        $baseUrl = $this->stringValue(config('app.url'));

        if ($baseUrl === null) {
            throw new InvalidArgumentException(
                'Application URL must be configured before resolving page canonical URLs.'
            );
        }

        return rtrim($baseUrl, '/')
            .($path === '/' ? '/' : '/'.ltrim($path, '/'));
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}