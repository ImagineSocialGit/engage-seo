<x-layouts.public :meta="$meta" :site="$site">
    <div data-blog-page="category" data-blog-category="{{ $category['slug'] }}">
        <header class="blog-header">
            <div class="site-container blog-header__inner">
                <p class="blog-eyebrow">
                    <a href="{{ $blogPath }}">
                        {{ $blog['title'] }}
                    </a>
                </p>

                <h1>{{ $category['name'] }}</h1>

                @if($category['description'])
                    <p class="blog-header__intro">{{ $category['description'] }}</p>
                @endif
            </div>
        </header>

        <section class="blog-region" aria-label="Category articles">
            <div class="site-container">
                <div class="blog-grid">
                    @foreach($posts as $post)
                        @include('features.blog.partials.post-card', ['post' => $post])
                    @endforeach
                </div>

                {{ $posts->links() }}
            </div>
        </section>
    </div>
</x-layouts.public>