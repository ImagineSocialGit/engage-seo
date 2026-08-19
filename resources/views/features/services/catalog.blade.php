@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $intro = $intro ?? null;
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';

    $buckets = app(\App\Features\Services\ServiceCatalog::class)
        ->selection(
            $group ?? null,
            $items ?? null,
        );
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-services section-card-grid"
    data-section-component="services"
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

        @foreach($buckets as $bucket)
            <div class="section-services__group">
                @if($bucket['title'] !== null || $bucket['intro'] !== null)
                    <div class="section__header">
                        @if($bucket['title'] !== null)
                            <h3 class="section-card__title">
                                {{ $bucket['title'] }}
                            </h3>
                        @endif

                        @if($bucket['intro'] !== null)
                            <p class="section__intro">
                                {{ $bucket['intro'] }}
                            </p>
                        @endif
                    </div>
                @endif

                <div class="section-card-grid__grid">
                    @foreach($bucket['items'] as $item)
                        <article class="section-card">
                            @if($item['image'] !== null)
                                <div class="section-card__media">
                                    <x-section-image :image="$item['image']" />
                                </div>
                            @endif

                            @if($bucket['title'] !== null)
                                <h4 class="section-card__title">
                                    {{ $item['title'] }}
                                </h4>
                            @else
                                <h3 class="section-card__title">
                                    {{ $item['title'] }}
                                </h3>
                            @endif

                            @if($item['summary'] !== null)
                                <p class="section-card__description">
                                    {{ $item['summary'] }}
                                </p>
                            @endif

                            @if($item['facts'] !== [])
                                <ul class="section__list">
                                    @foreach($item['facts'] as $fact)
                                        <li>
                                            <strong>{{ $fact['label'] }}:</strong>
                                            {{ $fact['value'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if($item['link'] !== null)
                                <div class="section-actions">
                                    <a
                                        href="{{ $item['link']['url'] }}"
                                        class="section-action"
                                        @if($item['link']['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                                    >
                                        {{ $item['link']['label'] }}
                                    </a>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>