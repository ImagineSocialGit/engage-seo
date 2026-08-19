<x-layouts.public :meta="$meta" :site="$site">
    <div data-blog-page="index">
        <header class="blog-header">
            <div class="site-container blog-header__inner">
                @if($blog['eyebrow'])
                    <p class="blog-eyebrow">{{ $blog['eyebrow'] }}</p>
                @endif

                <h1>{{ $blog['title'] }}</h1>

                @if($blog['intro'])
                    <p class="blog-header__intro">{{ $blog['intro'] }}</p>
                @endif

                @if($blog['actions'] !== [])
                    <div class="blog-actions">
                        @foreach($blog['actions'] as $action)
                            <a
                                href="{{ $action['url'] }}"
                                @if($action['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                            >
                                {{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </header>

        @if($featured !== [])
            <section class="blog-region" aria-labelledby="blog-featured-heading">
                <div class="site-container">
                    <h2 id="blog-featured-heading">
                        {{ $blog['featured_title'] ?? $blog['title'] }}
                    </h2>

                    <div class="blog-grid blog-grid--featured">
                        @foreach($featured as $post)
                            @include('features.blog.partials.post-card', ['post' => $post])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($categories !== [])
            <nav class="blog-region" aria-label="Article categories">
                <div class="site-container">
                    @if($blog['categories_title'])
                        <h2>{{ $blog['categories_title'] }}</h2>
                    @endif

                    <ul class="blog-category-list">
                        @foreach($categories as $category)
                            <li>
                                <a href="{{ $category['path'] }}">
                                    {{ $category['name'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        @endif

        <section class="blog-region" aria-label="Articles">
            <div class="site-container">
                <div class="blog-grid">
                    @foreach($posts as $post)
                        @include('features.blog.partials.post-card', ['post' => $post])
                    @endforeach
                </div>

                {{ $posts->links() }}
            </div>
        </section>

        @if($blog['footer_cta'])
            <aside class="blog-footer-cta">
                <div class="site-container blog-footer-cta__inner">
                    <div>
                        <h2>{{ $blog['footer_cta']['title'] }}</h2>

                        @if($blog['footer_cta']['description'])
                            <p>{{ $blog['footer_cta']['description'] }}</p>
                        @endif
                    </div>

                    <div class="blog-actions">
                        @foreach($blog['footer_cta']['actions'] as $action)
                            <a
                                href="{{ $action['url'] }}"
                                @if($action['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                            >
                                {{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </aside>
        @endif
    </div>
</x-layouts.public>