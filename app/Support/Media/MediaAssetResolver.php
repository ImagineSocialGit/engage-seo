<?php

namespace App\Support\Media;

use RuntimeException;

final class MediaAssetResolver
{
    public function __construct(
        private readonly MediaManifestRepository $manifest,
        private readonly MediaUrlResolver $urls,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $key): array
    {
        $asset = $this->manifest->asset($key);
        $fallback = $asset['fallback'] ?? null;
        $sources = $asset['sources'] ?? null;

        if (! is_array($fallback) || ! is_array($sources)) {
            throw new RuntimeException(
                "Generated media asset [{$key}] has an invalid rendering contract."
            );
        }

        $resolvedSources = [];
        $srcsets = [];

        foreach (['avif', 'webp'] as $format) {
            $entries = $sources[$format] ?? null;

            if (! is_array($entries) || ! array_is_list($entries) || $entries === []) {
                throw new RuntimeException(
                    "Generated media asset [{$key}] is missing [{$format}] sources."
                );
            }

            $resolvedSources[$format] = array_map(
                function (array $entry): array {
                    return [
                        'url' => $this->urls->resolve((string) $entry['path']),
                        'width' => $entry['width'],
                        'height' => $entry['height'],
                    ];
                },
                $entries,
            );

            $srcsets[$format] = implode(
                ', ',
                array_map(
                    fn (array $entry): string => $entry['url'].' '.$entry['width'].'w',
                    $resolvedSources[$format],
                ),
            );
        }

        return [
            'key' => $key,
            'width' => $asset['width'],
            'height' => $asset['height'],
            'placeholder' => $asset['placeholder'],
            'fallback' => [
                'url' => $this->urls->resolve((string) $fallback['path']),
                'width' => $fallback['width'],
                'height' => $fallback['height'],
            ],
            'sources' => $resolvedSources,
            'srcsets' => $srcsets,
        ];
    }
}