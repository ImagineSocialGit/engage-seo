<x-layouts.public :meta="$meta" :site="$site">
    <article data-blog-page="article" data-blog-post="{{ $post['slug'] }}" class="blog-article">
        <header class="blog-article__header">
            <div class="site-container blog-article__header-inner">
                <p class="blog-eyebrow">
                    <a href="{{ $blogPath }}">{{ $blog['title'] }}</a>
                </p>

                <h1>{{ $post['title'] }}</h1>

                @if($post['excerpt'])
                    <p class="blog-article__excerpt">{{ $post['excerpt'] }}</p>
                @endif

                <div class="blog-article__meta">
                    @if($post['author_name'])
                        <span>{{ $post['author_name'] }}</span>
                    @endif

                    @if($post['published_at'])
                        <time datetime="{{ $post['published_at']->toAtomString() }}">
                            {{ $post['published_at']->toFormattedDateString() }}
                        </time>
                    @endif
                </div>

                @if($post['categories'] !== [])
                    <nav aria-label="Article categories">
                        <ul class="blog-category-list">
                            @foreach($post['categories'] as $category)
                                <li>
                                    <a href="{{ $category['path'] }}">
                                        {{ $category['name'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>
        </header>

        @if($post['image'])
            <div class="site-container blog-article__featured-image">
                <x-responsive-image
                    :asset="$post['image']['asset']"
                    :alt="$post['image']['alt']"
                    :sizes="$post['image']['sizes']"
                    :loading="$post['image']['loading']"
                    :fetchpriority="$post['image']['fetchpriority']"
                />
            </div>
        @endif

        <div class="site-container blog-article__content">
            @include('features.blog.partials.content', [
                'blocks' => $post['content'],
            ])
        </div>
    </article>
</x-layouts.public>