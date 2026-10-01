<?php

namespace App\Features\Blog;

use App\Support\Site\SitePeopleResolver;

final class BlogAuthorResolver
{
    public function __construct(
        private readonly BlogConfiguration $configuration,
        private readonly SitePeopleResolver $people,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(?string $authorName): ?array
    {
        if (! is_string($authorName) || trim($authorName) === '') {
            return null;
        }

        $authorName = trim($authorName);
        $personKey = $this->configuration->authorProfiles()[$authorName] ?? null;

        if ($personKey === null) {
            return [
                'name' => $authorName,
                'profile' => null,
            ];
        }

        $profile = $this->people->find($personKey);

        return [
            'name' => $authorName,
            'profile' => $profile,
        ];
    }
}