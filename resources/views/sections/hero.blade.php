@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $subtitle = $subtitle ?? null;
    $content = $content ?? null;
    $actions = is_array($actions ?? null) ? $actions : [];
    $proof = is_array($proof ?? null) ? $proof : [];
    $media = $media ?? null;
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-hero"
    data-section-component="hero"
    data-section-theme="{{ $themeName }}"
    data-section-layout="{{ $layoutName }}"
>
    <div class="site-container section-hero__grid">
        <div class="section-hero__content">
            @if(is_string($eyebrow) && trim($eyebrow) !== '')
                <p class="section__eyebrow">{{ $eyebrow }}</p>
            @endif

            @if(is_string($title) && trim($title) !== '')
                <h1 class="section-hero__title">{{ $title }}</h1>
            @endif

            @if(is_string($subtitle) && trim($subtitle) !== '')
                <p class="section-hero__subtitle">{{ $subtitle }}</p>
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

            <x-section-actions :actions="$actions" />

            @if($proof !== [])
                <dl class="section-hero__proof">
                    @foreach($proof as $item)
                        @if(is_array($item))
                            <div class="section-hero__proof-item">
                                @if(is_string($item['value'] ?? null) && trim($item['value']) !== '')
                                    <dt class="section-hero__proof-value">{{ $item['value'] }}</dt>
                                @endif

                                @if(is_string($item['label'] ?? null) && trim($item['label']) !== '')
                                    <dd class="section-hero__proof-label">{{ $item['label'] }}</dd>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </dl>
            @endif
        </div>

        @if(is_array($media))
            <div class="section-hero__media">
                <x-section-image :image="$media" />
            </div>
        @endif
    </div>
</section>