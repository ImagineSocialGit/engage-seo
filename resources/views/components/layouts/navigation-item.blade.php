@props([
    'item',
])

@if($item['type'] === 'group')
    <details
        class="site-navigation-group"
        @if($item['active']) data-active="true" @endif
    >
        <summary>
            {{ $item['label'] }}
        </summary>

        <x-layouts.navigation-list
            :items="$item['children']"
            class="site-navigation-list--nested"
        />
    </details>
@else
    <a
        href="{{ $item['url'] }}"
        class="site-navigation-link"
        @if($item['active']) aria-current="page" @endif
        @if($item['new_tab']) target="_blank" rel="noopener noreferrer" @endif
    >
        {{ $item['label'] }}
    </a>
@endif