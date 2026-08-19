@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $items = is_array($items ?? null) ? $items : [];
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-stats"
    data-section-component="stats"
    data-section-theme="{{ $themeName }}"
    data-section-layout="{{ $layoutName }}"
>
    <div class="site-container section__container">
        @if(
            (is_string($eyebrow) && trim($eyebrow) !== '')
            || (is_string($title) && trim($title) !== '')
        )
            <div class="section__header">
                @if(is_string($eyebrow) && trim($eyebrow) !== '')
                    <p class="section__eyebrow">{{ $eyebrow }}</p>
                @endif

                @if(is_string($title) && trim($title) !== '')
                    <h2 class="section__title">{{ $title }}</h2>
                @endif
            </div>
        @endif

        <dl class="section-stats__grid">
            @foreach($items as $item)
                @if(is_array($item))
                    <div class="section-stat">
                        @if(is_string($item['value'] ?? null) && trim($item['value']) !== '')
                            <dt class="section-stat__value">{{ $item['value'] }}</dt>
                        @endif

                        @if(is_string($item['label'] ?? null) && trim($item['label']) !== '')
                            <dd class="section-stat__label">{{ $item['label'] }}</dd>
                        @endif
                    </div>
                @endif
            @endforeach
        </dl>
    </div>
</section>