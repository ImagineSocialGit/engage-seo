<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

final class SectionActions extends Component
{
    /**
     * @var list<array{label: string, url: string, variant: string}>
     */
    public array $items;

    /**
     * @param array<mixed> $actions
     */
    public function __construct(
        public array $actions = [],
    ) {
        if (! array_is_list($actions)) {
            throw new InvalidArgumentException(
                'Section [actions] must be a list.'
            );
        }

        $this->items = array_map(
            fn (mixed $action, int $index): array => $this->normalizeAction(
                $action,
                $index,
            ),
            $actions,
            array_keys($actions),
        );
    }

    public function render(): View
    {
        return view('components.section-actions');
    }

    /**
     * @return array{label: string, url: string, variant: string}
     */
    private function normalizeAction(
        mixed $action,
        int $index,
    ): array {
        if (! is_array($action)) {
            throw new InvalidArgumentException(
                "Section action [{$index}] must be an array."
            );
        }

        $unknown = array_values(array_diff(
            array_keys($action),
            ['label', 'url', 'variant'],
        ));

        if ($unknown !== []) {
            sort($unknown);

            throw new InvalidArgumentException(
                "Section action [{$index}] contains unsupported key(s): "
                .implode(', ', $unknown).'.'
            );
        }

        $label = $this->requiredString(
            $action['label'] ?? null,
            "Section action [{$index}.label]",
        );
        $url = $this->requiredString(
            $action['url'] ?? null,
            "Section action [{$index}.url]",
        );
        $variant = $action['variant'] ?? 'primary';

        if (! is_string($variant)
            || ! in_array(trim($variant), ['primary', 'secondary', 'text'], true)
        ) {
            throw new InvalidArgumentException(
                "Section action [{$index}.variant] must be primary, secondary, or text."
            );
        }

        $this->assertSafeUrl($url, $index);

        return [
            'label' => $label,
            'url' => $url,
            'variant' => trim($variant),
        ];
    }

    private function assertSafeUrl(
        string $url,
        int $index,
    ): void {
        if (
            str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
        ) {
            return;
        }

        if (
            str_starts_with($url, '#')
            && preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $url) === 1
        ) {
            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && trim($host) !== '') {
                return;
            }
        }

        if (
            in_array($scheme, ['mailto', 'tel'], true)
            && preg_match('/^(mailto|tel):[^\s]+$/i', $url) === 1
        ) {
            return;
        }

        throw new InvalidArgumentException(
            "Section action [{$index}.url] must be an absolute path, fragment, or valid http, https, mailto, or tel URL."
        );
    }

    private function requiredString(
        mixed $value,
        string $context,
    ): string {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "{$context} must be a non-blank string."
            );
        }

        return trim($value);
    }
}