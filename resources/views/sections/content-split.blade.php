@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $content = $content ?? null;
    $bullets = is_array($bullets ?? null) ? $bullets : [];
    $actions = is_array($actions ?? null) ? $actions : [];
    $media = $media ?? null;
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-content-split"
    data-section-component="content-split"
    data-section-theme="{{ $themeName }}"
    data-section-layout="{{ $layoutName }}"
>
    <div class="site-container section-content-split__grid">
        <div class="section-content-split__content">
            @if(is_string($eyebrow) && trim($eyebrow) !== '')
                <p class="section__eyebrow">{{ $eyebrow }}</p>
            @endif

            @if(is_string($title) && trim($title) !== '')
                <h2 class="section__title">{{ $title }}</h2>
            @endif

            @if(is_array($content))
                <div class="section__copy">
                    @foreach($content as $paragraph)
                        @if(is_string($paragraph) && trim($paragraph) !== '')
                            <p>{{ $paragraph }}</p>
                        @endif
                    @endforeach
                </div>
            @elseif(is_string($content) && trim($content) !== '')
                <div class="section__copy">
                    <p>{{ $content }}</p>
                </div>
            @endif

            @if($bullets !== [])
                <ul class="section__list">
                    @foreach($bullets as $bullet)
                        @if(is_string($bullet) && trim($bullet) !== '')
                            <li>{{ $bullet }}</li>
                        @endif
                    @endforeach
                </ul>
            @endif

            <x-section-actions :actions="$actions" />
        </div>

        @if(is_array($media))
            <div class="section-content-split__media">
                <x-section-image :image="$media" />
            </div>
        @endif
    </div>
</section>