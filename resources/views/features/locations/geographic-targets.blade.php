@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $intro = $intro ?? null;
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';

    $targetSelection = app(\App\Features\Locations\GeographicTargetCatalog::class)
        ->selection(
            $parent ?? null,
            $targets ?? null,
        );
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-geographic-targets section-card-grid"
    data-section-component="geographic-targets"
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
            @foreach($targetSelection as $target)
                <article
                    class="section-card"
                    data-geographic-target="{{ $target['key'] }}"
                    data-geographic-target-type="{{ $target['type'] }}"
                >
                    <h3 class="section-card__title">
                        @if($target['page'] !== null)
                            <a
                                href="{{ $target['page']['path'] }}"
                                class="section-action"
                                data-variant="text"
                            >
                                {{ $target['title'] }}
                            </a>
                        @else
                            {{ $target['title'] }}
                        @endif
                    </h3>

                    @if($target['summary'] !== null)
                        <p class="section-card__description">
                            {{ $target['summary'] }}
                        </p>
                    @endif

                    @if($target['address'] !== [])
                        <address class="section-card__description">
                            @foreach($target['address'] as $line)
                                <span>{{ $line }}</span>@if(! $loop->last)<br>@endif
                            @endforeach
                        </address>
                    @endif

                    @if($target['children'] !== [])
                        <ul class="section__list" aria-label="Areas in {{ $target['title'] }}">
                            @foreach($target['children'] as $child)
                                <li>
                                    @if($child['page'] !== null)
                                        <a
                                            href="{{ $child['page']['path'] }}"
                                            class="section-action"
                                            data-variant="text"
                                        >
                                            {{ $child['title'] }}
                                        </a>
                                    @else
                                        {{ $child['title'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>