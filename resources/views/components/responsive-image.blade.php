<picture>
    <source
        type="image/avif"
        srcset="{{ $media['srcsets']['avif'] }}"
        sizes="{{ $sizes }}"
    >
    <source
        type="image/webp"
        srcset="{{ $media['srcsets']['webp'] }}"
        sizes="{{ $sizes }}"
    >
    <img
        {{ $attributes->except(['src', 'alt', 'width', 'height', 'loading', 'fetchpriority', 'decoding']) }}
        src="{{ $media['fallback']['url'] }}"
        alt="{{ $alt }}"
        width="{{ $media['fallback']['width'] }}"
        height="{{ $media['fallback']['height'] }}"
        loading="{{ $loading }}"
        fetchpriority="{{ $fetchpriority }}"
        decoding="{{ $decoding }}"
    >
</picture>