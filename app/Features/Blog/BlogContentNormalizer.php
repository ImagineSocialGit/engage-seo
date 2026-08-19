<?php

namespace App\Features\Blog;

use App\Support\Site\SiteLinkNormalizer;
use InvalidArgumentException;

final class BlogContentNormalizer
{
    public function __construct(
        private readonly SiteLinkNormalizer $links,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function normalize(mixed $content): array
    {
        if (! is_array($content)
            || ! array_is_list($content)
            || $content === []
        ) {
            throw new InvalidArgumentException(
                'Blog post content must be a non-empty list of structured blocks.'
            );
        }

        $normalized = [];

        foreach ($content as $index => $block) {
            if (! is_array($block)) {
                throw new InvalidArgumentException(
                    "Blog post content block [{$index}] must be an array."
                );
            }

            $type = $this->requiredString(
                $block['type'] ?? null,
                "Blog post content block [{$index}.type]",
            );

            $normalized[] = match ($type) {
                'paragraph' => $this->paragraph($block, $index),
                'heading' => $this->heading($block, $index),
                'list' => $this->listBlock($block, $index),
                'quote' => $this->quote($block, $index),
                'links' => $this->linksBlock($block, $index),
                'image' => $this->image($block, $index),
                default => throw new InvalidArgumentException(
                    "Blog post content block [{$index}] has unsupported type [{$type}]."
                ),
            };
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function paragraph(array $block, int $index): array
    {
        $this->assertOnlyKeys(
            $block,
            ['type', 'text'],
            "Blog post content block [{$index}]",
        );

        return [
            'type' => 'paragraph',
            'text' => $this->requiredString(
                $block['text'] ?? null,
                "Blog post content block [{$index}.text]",
            ),
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function heading(array $block, int $index): array
    {
        $this->assertOnlyKeys(
            $block,
            ['type', 'text', 'level'],
            "Blog post content block [{$index}]",
        );

        $level = $block['level'] ?? 2;

        if (! is_int($level) || ! in_array($level, [2, 3], true)) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.level] must be 2 or 3."
            );
        }

        return [
            'type' => 'heading',
            'level' => $level,
            'text' => $this->requiredString(
                $block['text'] ?? null,
                "Blog post content block [{$index}.text]",
            ),
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function listBlock(array $block, int $index): array
    {
        $this->assertOnlyKeys(
            $block,
            ['type', 'items', 'ordered'],
            "Blog post content block [{$index}]",
        );

        $items = $block['items'] ?? null;

        if (! is_array($items)
            || ! array_is_list($items)
            || $items === []
        ) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.items] must be a non-empty list."
            );
        }

        $normalizedItems = [];

        foreach ($items as $itemIndex => $item) {
            $normalizedItems[] = $this->requiredString(
                $item,
                "Blog post content block [{$index}.items.{$itemIndex}]",
            );
        }

        $ordered = $block['ordered'] ?? false;

        if (! is_bool($ordered)) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.ordered] must be a boolean."
            );
        }

        return [
            'type' => 'list',
            'ordered' => $ordered,
            'items' => $normalizedItems,
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function quote(array $block, int $index): array
    {
        $this->assertOnlyKeys(
            $block,
            ['type', 'text', 'attribution'],
            "Blog post content block [{$index}]",
        );

        return [
            'type' => 'quote',
            'text' => $this->requiredString(
                $block['text'] ?? null,
                "Blog post content block [{$index}.text]",
            ),
            'attribution' => $this->optionalString(
                $block['attribution'] ?? null,
                "Blog post content block [{$index}.attribution]",
            ),
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function linksBlock(array $block, int $index): array
    {
        $this->assertOnlyKeys(
            $block,
            ['type', 'items'],
            "Blog post content block [{$index}]",
        );

        $items = $this->links->links(
            $block['items'] ?? [],
            '/',
            "features.blog.content.{$index}.items",
            ['http', 'https', 'mailto', 'tel'],
            true,
            false,
        );

        if ($items === []) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.items] must contain at least one link."
            );
        }

        return [
            'type' => 'links',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function image(array $block, int $index): array
    {
        $this->assertOnlyKeys(
            $block,
            [
                'type',
                'asset',
                'alt',
                'caption',
                'sizes',
                'loading',
                'fetchpriority',
            ],
            "Blog post content block [{$index}]",
        );

        if (! array_key_exists('alt', $block)
            || ! is_string($block['alt'])
        ) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.alt] must be explicitly provided as a string."
            );
        }

        $loading = $this->optionalString(
            $block['loading'] ?? 'lazy',
            "Blog post content block [{$index}.loading]",
        );

        if (! in_array($loading, ['lazy', 'eager'], true)) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.loading] must be lazy or eager."
            );
        }

        $fetchpriority = $this->optionalString(
            $block['fetchpriority'] ?? 'auto',
            "Blog post content block [{$index}.fetchpriority]",
        );

        if (! in_array($fetchpriority, ['auto', 'high', 'low'], true)) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.fetchpriority] must be auto, high, or low."
            );
        }

        $sizes = $this->optionalString(
            $block['sizes'] ?? '100vw',
            "Blog post content block [{$index}.sizes]",
        );

        if ($sizes === null) {
            throw new InvalidArgumentException(
                "Blog post content block [{$index}.sizes] must not be blank."
            );
        }

        return [
            'type' => 'image',
            'asset' => $this->requiredString(
                $block['asset'] ?? null,
                "Blog post content block [{$index}.asset]",
            ),
            'alt' => $block['alt'],
            'caption' => $this->optionalString(
                $block['caption'] ?? null,
                "Blog post content block [{$index}.caption]",
            ),
            'sizes' => $sizes,
            'loading' => $loading,
            'fetchpriority' => $fetchpriority,
        ];
    }

    private function requiredString(mixed $value, string $context): string
    {
        $value = $this->optionalString($value, $context);

        if ($value === null) {
            throw new InvalidArgumentException(
                "{$context} must be a non-blank string."
            );
        }

        return $value;
    }

    private function optionalString(mixed $value, string $context): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "{$context} must be null or a string."
            );
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<mixed> $values
     * @param list<string> $allowedKeys
     */
    private function assertOnlyKeys(
        array $values,
        array $allowedKeys,
        string $context,
    ): void {
        $unknown = array_values(array_diff(
            array_map(
                static fn (mixed $key): string => (string) $key,
                array_keys($values),
            ),
            $allowedKeys,
        ));

        sort($unknown);

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                '%s contains unsupported key(s): %s.',
                $context,
                implode(', ', $unknown),
            ));
        }
    }
}