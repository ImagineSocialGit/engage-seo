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
    class="section section-steps"
    data-section-component="steps"
    data-section-theme="{{ $themeName }}"
    data-section-layout="{{ $layoutName }}"
>
    <div class="site-container section__container">
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

        <ol class="section-steps__grid">
            @foreach($items as $index => $item)
                @if(is_array($item))
                    <li class="section-step">
                        <div class="section-step__number">
                            {{ is_string($item['step'] ?? null) && trim($item['step']) !== ''
                                ? $item['step']
                                : $index + 1 }}
                        </div>

                        @if(is_string($item['title'] ?? null) && trim($item['title']) !== '')
                            <h3 class="section-step__title">{{ $item['title'] }}</h3>
                        @endif

                        @if(is_array($item['content'] ?? null))
                            <div class="section__copy">
                                @foreach($item['content'] as $paragraph)
                                    @if(is_string($paragraph) && trim($paragraph) !== '')
                                        <p>{{ $paragraph }}</p>
                                    @endif
                                @endforeach
                            </div>
                        @elseif(is_string($item['content'] ?? null) && trim($item['content']) !== '')
                            <div class="section__copy">
                                <p>{{ $item['content'] }}</p>
                            </div>
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
                    </li>
                @endif
            @endforeach
        </ol>
    </div>
</section>