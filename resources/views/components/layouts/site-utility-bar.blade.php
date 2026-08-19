@props([
    'site',
])

@php
    $utility = $site['shell']['utility_bar'];
@endphp

<div
    class="site-utility-bar shell-region"
    data-shell-theme="{{ $utility['theme'] }}"
>
    <div class="site-container site-utility-bar__inner">
        <ul class="site-utility-bar__items">
            @foreach($utility['items'] as $item)
                <li class="site-utility-bar__item">
                    @if($item['type'] === 'text')
                        <span>{{ $item['text'] }}</span>
                    @else
                        <a
                            href="{{ $item['url'] }}"
                            class="site-utility-bar__link"
                            @if($item['active']) aria-current="page" @endif
                            @if($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                        >
                            {{ $item['label'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>