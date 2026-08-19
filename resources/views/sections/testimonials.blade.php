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
    class="section section-testimonials"
    data-section-component="testimonials"
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

        <div class="section-testimonials__grid">
            @foreach($items as $item)
                @if(is_array($item))
                    @php
                        $rating = null;

                        if (array_key_exists('rating', $item)) {
                            if (! is_int($item['rating']) || $item['rating'] < 1 || $item['rating'] > 5) {
                                throw new \InvalidArgumentException(
                                    'Testimonial [rating] must be an integer from 1 through 5.'
                                );
                            }

                            $rating = $item['rating'];
                        }
                    @endphp

                    <figure class="section-testimonial">
                        @if($rating !== null)
                            <div
                                class="section-testimonial__rating"
                                aria-label="{{ $rating }} out of 5 stars"
                            >
                                {{ str_repeat('★', $rating) }}
                            </div>
                        @endif

                        @if(is_string($item['quote'] ?? null) && trim($item['quote']) !== '')
                            <blockquote class="section-testimonial__quote">
                                <p>{{ $item['quote'] }}</p>
                            </blockquote>
                        @endif

                        @if(
                            (is_string($item['name'] ?? null) && trim($item['name']) !== '')
                            || (is_string($item['context'] ?? null) && trim($item['context']) !== '')
                            || (is_string($item['source'] ?? null) && trim($item['source']) !== '')
                        )
                            <figcaption class="section-testimonial__caption">
                                @if(is_string($item['name'] ?? null) && trim($item['name']) !== '')
                                    <span class="section-testimonial__name">{{ $item['name'] }}</span>
                                @endif

                                @if(is_string($item['context'] ?? null) && trim($item['context']) !== '')
                                    <span>{{ $item['context'] }}</span>
                                @endif

                                @if(is_string($item['source'] ?? null) && trim($item['source']) !== '')
                                    <span>{{ $item['source'] }}</span>
                                @endif
                            </figcaption>
                        @endif
                    </figure>
                @endif
            @endforeach
        </div>
    </div>
</section>