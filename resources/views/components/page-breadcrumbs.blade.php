@props([
    'items' => [],
])

@if($items !== [])
    <nav class="page-breadcrumbs" aria-label="Breadcrumb">
        <div class="site-container">
            <ol class="page-breadcrumbs__list">
                @foreach($items as $item)
                    <li class="page-breadcrumbs__item">
                        @if(! $loop->last)
                            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                        @else
                            <span aria-current="page">{{ $item['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </nav>
@endif