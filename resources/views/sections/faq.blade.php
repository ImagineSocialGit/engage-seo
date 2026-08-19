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
    class="section section-faq"
    data-section-component="faq"
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

        <div class="section-faq__list">
            @foreach($items as $item)
                @if(is_array($item))
                    <details class="section-faq__item">
                        @if(is_string($item['question'] ?? null) && trim($item['question']) !== '')
                            <summary class="section-faq__question">
                                {{ $item['question'] }}
                            </summary>
                        @endif

                        @if(is_array($item['answer'] ?? null))
                            <div class="section-faq__answer">
                                @foreach($item['answer'] as $paragraph)
                                    @if(is_string($paragraph) && trim($paragraph) !== '')
                                        <p>{{ $paragraph }}</p>
                                    @endif
                                @endforeach
                            </div>
                        @elseif(is_string($item['answer'] ?? null) && trim($item['answer']) !== '')
                            <div class="section-faq__answer">
                                <p>{{ $item['answer'] }}</p>
                            </div>
                        @endif
                    </details>
                @endif
            @endforeach
        </div>
    </div>
</section>