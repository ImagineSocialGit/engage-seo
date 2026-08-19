@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $intro = $intro ?? null;
    $items = is_array($items ?? null) ? $items : [];
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-card-grid"
    data-section-component="card-grid"
    data-section-theme="{{ $themeName }}"
    data-section-layout="{{ $layoutName }}"
>
    <div class="site-container section__container">
        @if(
            (is_string($eyebrow) && trim($eyebrow) !== '')
            || (is_string($title) && trim($title) !== '')
            || (is_string($intro) && trim($intro) !== '')
        )
            <div class="section__header">
                @if(is_string($eyebrow) && trim($eyebrow) !== '')
                    <p class="section__eyebrow">{{ $eyebrow }}</p>
                @endif

                @if(is_string($title) && trim($title) !== '')
                    <h2 class="section__title">{{ $title }}</h2>
                @endif

                @if(is_string($intro) && trim($intro) !== '')
                    <p class="section__intro">{{ $intro }}</p>
                @endif
            </div>
        @endif

        <div class="section-card-grid__grid">
            @foreach($items as $item)
                @if(is_array($item))
                    <article class="section-card">
                        @if(is_string($item['eyebrow'] ?? null) && trim($item['eyebrow']) !== '')
                            <p class="section-card__eyebrow">{{ $item['eyebrow'] }}</p>
                        @endif

                        @if(is_string($item['title'] ?? null) && trim($item['title']) !== '')
                            <h3 class="section-card__title">{{ $item['title'] }}</h3>
                        @endif

                        @if(is_string($item['description'] ?? null) && trim($item['description']) !== '')
                            <p class="section-card__description">{{ $item['description'] }}</p>
                        @endif

                        @if(is_array($item['bullets'] ?? null) && $item['bullets'] !== [])
                            <ul class="section__list">
                                @foreach($item['bullets'] as $bullet)
                                    @if(is_string($bullet) && trim($bullet) !== '')
                                        <li>{{ $bullet }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif

                        @if(is_array($item['action'] ?? null))
                            <x-section-actions :actions="[$item['action']]" />
                        @endif
                    </article>
                @endif
            @endforeach
        </div>
    </div>
</section>