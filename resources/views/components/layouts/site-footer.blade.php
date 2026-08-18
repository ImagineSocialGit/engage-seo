@props([
    'site',
])

@php
    $footer = $site['shell']['footer'];
@endphp

<div class="site-container site-footer__inner">
    <a href="/" class="site-footer__brand">
        {{ $site['name'] }}
    </a>

    @if($footer['items'] !== [])
        <nav aria-label="Footer navigation">
            <x-layouts.navigation-list
                :items="$footer['items']"
                class="site-navigation-list--footer"
            />
        </nav>
    @endif
</div>