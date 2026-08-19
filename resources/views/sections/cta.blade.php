@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $content = $content ?? null;
    $actions = is_array($actions ?? null) ? $actions : [];
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-cta"
    data-section-component="cta"
    data-section-theme="{{ $themeName }}"
    data-section-layout="{{ $layoutName }}"
>
    <div class="site-container section-cta__inner">
        <div class="section-cta__content">
            @if(is_string($eyebrow) && trim($eyebrow) !== '')
                <p class="section__eyebrow">{{ $eyebrow }}</p>
            @endif

            @if(is_string($title) && trim($title) !== '')
                <h2 class="section__title">{{ $title }}</h2>
            @endif

            @if(is_string($content) && trim($content) !== '')
                <p class="section__intro">{{ $content }}</p>
            @endif
        </div>

        <x-section-actions :actions="$actions" />
    </div>
</section>