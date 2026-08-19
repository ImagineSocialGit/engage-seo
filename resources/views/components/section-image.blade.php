@props([
    'image',
])

@php
    if (! is_array($image)) {
        throw new \InvalidArgumentException('Section image must be an array.');
    }

    $asset = $image['asset'] ?? null;

    if (! is_string($asset) || trim($asset) === '') {
        throw new \InvalidArgumentException('Section image [asset] must be a non-blank string.');
    }

    if (! array_key_exists('alt', $image) || ! is_string($image['alt'])) {
        throw new \InvalidArgumentException('Section image [alt] must be explicitly provided as a string.');
    }

    $sizes = is_string($image['sizes'] ?? null) && trim($image['sizes']) !== ''
        ? trim($image['sizes'])
        : '100vw';

    $loading = is_string($image['loading'] ?? null) && trim($image['loading']) !== ''
        ? trim($image['loading'])
        : 'lazy';

    $fetchpriority = is_string($image['fetchpriority'] ?? null) && trim($image['fetchpriority']) !== ''
        ? trim($image['fetchpriority'])
        : 'auto';
@endphp

<x-responsive-image
    :asset="trim($asset)"
    :alt="$image['alt']"
    :sizes="$sizes"
    :loading="$loading"
    :fetchpriority="$fetchpriority"
/>