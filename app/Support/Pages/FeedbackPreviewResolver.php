<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class FeedbackPreviewResolver
{
    public function __construct(private readonly Repository $config)
    {
    }

    /** @return array<string, mixed>|null */
    public function resolve(array $page, Request $request): ?array
    {
        $definition = $this->config->get('feedback_preview');

        if (! is_array($definition) || ($definition['page_key'] ?? null) !== ($page['key'] ?? null)) {
            return null;
        }

        $value = $request->query('t');
        $state = is_string($value) && in_array($value, ['great', 'not-great'], true)
            ? $value : 'choose';
        $links = $definition['review_links'] ?? [];

        if (! is_array($links) || ! array_is_list($links)) {
            throw new InvalidArgumentException('Feedback review links must be a list.');
        }

        foreach ($links as $link) {
            if (! is_array($link) || ! is_string($link['label'] ?? null)
                || trim($link['label']) === '' || ! is_string($link['url'] ?? null)
                || preg_match('/[\s\x00-\x1F\x7F\\\\]/', $link['url']) === 1) {
                throw new InvalidArgumentException('Invalid feedback review link.');
            }

            $url = parse_url($link['url']);

            if (! is_array($url) || ($url['scheme'] ?? null) !== 'https'
                || empty($url['host']) || isset($url['user']) || isset($url['pass'])) {
                throw new InvalidArgumentException('Feedback review links require public HTTPS URLs.');
            }
        }

        $path = $page['path'];

        return [
            'state' => $state,
            'positive_url' => $path.'?t=great',
            'negative_url' => $path.'?t=not-great',
            'review_links' => $links,
            'submission_mode' => 'preview',
        ];
    }
}