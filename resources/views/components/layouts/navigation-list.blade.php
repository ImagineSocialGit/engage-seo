@props([
    'items' => [],
])

<ul {{ $attributes->class(['site-navigation-list']) }}>
    @foreach($items as $item)
        <li class="site-navigation-list__item">
            <x-layouts.navigation-item :item="$item" />
        </li>
    @endforeach
</ul>