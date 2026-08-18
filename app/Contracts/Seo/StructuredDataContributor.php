<?php

namespace App\Contracts\Seo;

interface StructuredDataContributor
{
    /**
     * Return JSON-LD nodes that should be rendered for the resolved page.
     *
     * @param array<string, mixed> $page
     * @param array<string, mixed> $site
     * @return list<array<string, mixed>>
     */
    public function structuredData(array $page, array $site): array;
}