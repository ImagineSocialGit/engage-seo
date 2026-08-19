@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? null;
    $content = $content ?? null;
    $embed = $embed ?? null;
    $actions = is_array($actions ?? null) ? $actions : [];
    $themeName = $theme ?? 'default';
    $layoutName = $layout ?? 'default';
@endphp

<section
    @if($id) id="{{ $id }}" @endif
    class="section section-media-embed"
    data-section-component="media-embed"
    data-section-theme="{{ $themeName }}"
    data-section-layout="{{ $layoutName }}"
>
    <div class="site-container section-media-embed__grid">
        <div class="section-media-embed__content">
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

            <x-section-actions :actions="$actions" />
        </div>

        @if($embed !== null)
            @if(! is_array($embed))
                @php
                    throw new \InvalidArgumentException('Section media embed [embed] must be null or an array.');
                @endphp
            @endif

            <div class="section-media-embed__media">
                <x-embed-frame
                    :src="$embed['src'] ?? ''"
                    :title="$embed['title'] ?? ''"
                    :loading="$embed['loading'] ?? 'lazy'"
                />
            </div>
        @endif
    </div>
</section>