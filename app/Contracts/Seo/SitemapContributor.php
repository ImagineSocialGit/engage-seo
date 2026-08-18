<?php

namespace App\Contracts\Seo;

interface SitemapContributor
{
    /**
     * Return public, indexable URLs owned by the selected site.
     *
     * @return list<array{loc: string}>
     */
    public function sitemapEntries(): array;
}