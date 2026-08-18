<?php

namespace App\Support\Seo;

use App\Support\Pages\PageRepository;
use RuntimeException;

final class SitemapBuilder
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly SeoIndexingPolicy $indexing,
        private readonly SeoExtensionRegistry $extensions,
    ) {
    }

    /**
     * @return list<array{loc: string}>
     */
    public function entries(): array
    {
        if (! $this->indexing->sitemapEnabled()) {
            return [];
        }

        $entries = [];

        foreach ($this->pages->normalizedPages() as $page) {
            if (! ($page['meta']['indexable'] ?? false)) {
                continue;
            }

            $this->addEntry($entries, [
                'loc' => $page['meta']['canonical'],
            ]);
        }

        foreach ($this->extensions->sitemapContributors() as $contributor) {
            $contributed = $contributor->sitemapEntries();

            if (! is_array($contributed) || ! array_is_list($contributed)) {
                throw new RuntimeException(
                    sprintf(
                        'SEO sitemap contributor [%s] must return a list.',
                        $contributor::class,
                    )
                );
            }

            foreach ($contributed as $entry) {
                $this->addEntry($entries, $entry, $contributor::class);
            }
        }

        return array_values($entries);
    }

    public function url(): string
    {
        $baseUrl = config('app.url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new RuntimeException(
                'Application URL must be configured before resolving the sitemap URL.'
            );
        }

        return rtrim($baseUrl, '/').'/sitemap.xml';
    }

    /**
     * @param array<string, array{loc: string}> $entries
     */
    private function addEntry(
        array &$entries,
        mixed $entry,
        ?string $contributor = null,
    ): void {
        $source = $contributor === null
            ? 'configured page sitemap'
            : "SEO sitemap contributor [{$contributor}]";

        if (! is_array($entry)) {
            throw new RuntimeException(
                "{$source} entries must be arrays."
            );
        }

        $unknown = array_values(array_diff(
            array_keys($entry),
            ['loc'],
        ));

        if ($unknown !== []) {
            throw new RuntimeException(sprintf(
                '%s entry contains unsupported key(s): %s.',
                $source,
                implode(', ', $unknown),
            ));
        }

        $location = $entry['loc'] ?? null;

        if (! is_string($location) || trim($location) === '') {
            throw new RuntimeException(
                "{$source} entry requires a non-blank [loc]."
            );
        }

        $location = trim($location);

        $this->assertSiteUrl($location, $source);

        $entries[$location] = [
            'loc' => $location,
        ];
    }

    private function assertSiteUrl(string $url, string $source): void
    {
        $baseUrl = config('app.url');

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new RuntimeException(
                'Application URL must be configured before building the sitemap.'
            );
        }

        $base = $this->origin($baseUrl);
        $candidate = $this->origin($url);

        if ($base === null || $candidate === null || $base !== $candidate) {
            throw new RuntimeException(
                "{$source} entry [{$url}] must use the selected site's APP_URL origin."
            );
        }
    }

    private function origin(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || trim($host) === ''
        ) {
            return null;
        }

        $origin = strtolower($scheme).'://'.strtolower($host);

        if (is_int($port)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }
}