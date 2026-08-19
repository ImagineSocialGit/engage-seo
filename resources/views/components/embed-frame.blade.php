<div {{ $attributes->class(['embed-frame']) }}>
    <iframe
        src="{{ $src }}"
        title="{{ $title }}"
        loading="{{ $loading }}"
        referrerpolicy="strict-origin-when-cross-origin"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen
    ></iframe>
</div>