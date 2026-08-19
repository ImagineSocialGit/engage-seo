@foreach($blocks as $block)
    @if($block['type'] === 'paragraph')
        <p>{{ $block['text'] }}</p>
    @elseif($block['type'] === 'heading')
        @if($block['level'] === 2)
            <h2>{{ $block['text'] }}</h2>
        @else
            <h3>{{ $block['text'] }}</h3>
        @endif
    @elseif($block['type'] === 'list')
        @if($block['ordered'])
            <ol>
                @foreach($block['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ol>
        @else
            <ul>
                @foreach($block['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
    @elseif($block['type'] === 'quote')
        <blockquote>
            <p>{{ $block['text'] }}</p>
            @if($block['attribution'])
                <footer>{{ $block['attribution'] }}</footer>
            @endif
        </blockquote>
    @elseif($block['type'] === 'links')
        <nav aria-label="Related links">
            <ul class="blog-content-links">
                @foreach($block['items'] as $link)
                    <li>
                        <a
                            href="{{ $link['url'] }}"
                            @if($link['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                        >
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @elseif($block['type'] === 'image')
        <figure>
            <x-responsive-image
                :asset="$block['asset']"
                :alt="$block['alt']"
                :sizes="$block['sizes']"
                :loading="$block['loading']"
                :fetchpriority="$block['fetchpriority']"
            />

            @if($block['caption'])
                <figcaption>{{ $block['caption'] }}</figcaption>
            @endif
        </figure>
    @endif
@endforeach