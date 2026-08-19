<article data-blog-post-card="{{ $post['slug'] }}" class="blog-card">
    @if($post['image'])
        <a href="{{ $post['path'] }}" tabindex="-1" aria-hidden="true" class="blog-card__image">
            <x-responsive-image
                :asset="$post['image']['asset']"
                :alt="$post['image']['alt']"
                :sizes="$post['image']['sizes']"
                :loading="$post['image']['loading']"
                :fetchpriority="$post['image']['fetchpriority']"
            />
        </a>
    @endif

    <div class="blog-card__body">
        @if($post['categories'] !== [])
            <ul class="blog-card__categories" aria-label="Article categories">
                @foreach($post['categories'] as $category)
                    <li>
                        <a href="{{ $category['path'] }}">{{ $category['name'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        <h2>
            <a href="{{ $post['path'] }}">{{ $post['title'] }}</a>
        </h2>

        @if($post['excerpt'])
            <p>{{ $post['excerpt'] }}</p>
        @endif

        @if($post['published_at'])
            <time datetime="{{ $post['published_at']->toAtomString() }}">
                {{ $post['published_at']->toFormattedDateString() }}
            </time>
        @endif
    </div>
</article>