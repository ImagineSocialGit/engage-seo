<?php

namespace App\Support\Seo;

use App\Contracts\Seo\SitemapContributor;
use App\Contracts\Seo\StructuredDataContributor;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class SeoExtensionRegistry
{
    /**
     * @var list<class-string<SitemapContributor>>
     */
    private array $sitemapContributors = [];

    /**
     * @var list<class-string<StructuredDataContributor>>
     */
    private array $structuredDataContributors = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param class-string<SitemapContributor> $contributor
     */
    public function registerSitemapContributor(string $contributor): void
    {
        if (! class_exists($contributor)
            || ! is_subclass_of($contributor, SitemapContributor::class)
        ) {
            throw new InvalidArgumentException(
                "SEO sitemap contributor must implement ".SitemapContributor::class.": {$contributor}"
            );
        }

        if (! in_array($contributor, $this->sitemapContributors, true)) {
            $this->sitemapContributors[] = $contributor;
        }
    }

    /**
     * @param class-string<StructuredDataContributor> $contributor
     */
    public function registerStructuredDataContributor(string $contributor): void
    {
        if (! class_exists($contributor)
            || ! is_subclass_of($contributor, StructuredDataContributor::class)
        ) {
            throw new InvalidArgumentException(
                "SEO structured-data contributor must implement ".StructuredDataContributor::class.": {$contributor}"
            );
        }

        if (! in_array($contributor, $this->structuredDataContributors, true)) {
            $this->structuredDataContributors[] = $contributor;
        }
    }

    /**
     * @return list<SitemapContributor>
     */
    public function sitemapContributors(): array
    {
        return array_map(
            fn (string $contributor): SitemapContributor => $this->container->make($contributor),
            $this->sitemapContributors,
        );
    }

    /**
     * @return list<StructuredDataContributor>
     */
    public function structuredDataContributors(): array
    {
        return array_map(
            fn (string $contributor): StructuredDataContributor => $this->container->make($contributor),
            $this->structuredDataContributors,
        );
    }
}