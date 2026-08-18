<?php

namespace App\Support\Seo;

use RuntimeException;

final class StructuredDataResolver
{
    public function __construct(
        private readonly SeoExtensionRegistry $extensions,
    ) {
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $site
     * @return list<array<string, mixed>>
     */
    public function resolve(array $page, array $site): array
    {
        $nodes = $page['meta']['structured_data'] ?? [];

        if (! is_array($nodes) || ! array_is_list($nodes)) {
            throw new RuntimeException(
                'Resolved page structured data must be a list.'
            );
        }

        foreach ($nodes as $node) {
            if (! is_array($node) || array_is_list($node)) {
                throw new RuntimeException(
                    'Resolved page structured-data nodes must be associative arrays.'
                );
            }
        }

        foreach ($this->extensions->structuredDataContributors() as $contributor) {
            $contributed = $contributor->structuredData($page, $site);

            if (! is_array($contributed) || ! array_is_list($contributed)) {
                throw new RuntimeException(sprintf(
                    'SEO structured-data contributor [%s] must return a list.',
                    $contributor::class,
                ));
            }

            foreach ($contributed as $node) {
                if (! is_array($node) || array_is_list($node)) {
                    throw new RuntimeException(sprintf(
                        'SEO structured-data contributor [%s] must return associative-array nodes.',
                        $contributor::class,
                    ));
                }

                $nodes[] = $node;
            }
        }

        return array_values($nodes);
    }
}