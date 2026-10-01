<?php

namespace App\Support\Seo;

use App\Contracts\Seo\StructuredDataContributor;
use InvalidArgumentException;

final class SiteOrganizationStructuredDataContributor implements StructuredDataContributor
{
    public function structuredData(array $page, array $site): array
    {
        $baseUrl = $this->baseUrl();
        $business = $site['business'] ?? [];

        if (! is_array($business)) {
            $business = [];
        }

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $baseUrl.'/#organization',
            'name' => $this->siteName($site),
            'url' => $baseUrl.'/',
        ];

        $phone = $business['phone']['value'] ?? null;

        if (is_string($phone) && trim($phone) !== '') {
            $node['telephone'] = trim($phone);
        }

        $email = $business['email']['value'] ?? null;

        if (is_string($email) && trim($email) !== '') {
            $node['email'] = trim($email);
        }

        $sameAs = [];

        foreach (($business['social_links'] ?? []) as $link) {
            if (! is_array($link)) {
                continue;
            }

            $url = $link['url'] ?? null;

            if (is_string($url) && preg_match('#^https?://#i', $url) === 1) {
                $sameAs[] = $url;
            }
        }

        if ($sameAs !== []) {
            $node['sameAs'] = array_values(array_unique($sameAs));
        }

        return [$node];
    }

    private function baseUrl(): string
    {
        $url = config('app.url');

        if (! is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException(
                'Application URL must be configured before resolving Organization structured data.'
            );
        }

        return rtrim(trim($url), '/');
    }

    private function siteName(array $site): string
    {
        $name = $site['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException(
                'Site presentation must provide a name before resolving Organization structured data.'
            );
        }

        return trim($name);
    }
}