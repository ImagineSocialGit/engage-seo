@props([
    'site',
])

@php
    $brand = $site['brand'];
    $navigation = $site['shell']['navigation'];
    $hasNavigation = $navigation['enabled']
        && ($navigation['items'] !== [] || $navigation['primary_cta'] !== null);
@endphp

<div class="site-container site-header__inner">
    <a href="/" class="site-brand">
        @if($brand['logo'])
            <img
                src="{{ $brand['logo'] }}"
                alt="{{ $brand['logo_alt'] }}"
                class="site-brand__logo"
            >
        @else
            <span class="site-brand__name">
                {{ $site['name'] }}
            </span>
        @endif
    </a>

    @if($hasNavigation)
        <div class="site-navigation-desktop">
            @if($navigation['items'] !== [])
                <nav aria-label="Primary navigation">
                    <x-layouts.navigation-list
                        :items="$navigation['items']"
                        class="site-navigation-list--desktop"
                    />
                </nav>
            @endif

            @if($navigation['primary_cta'])
                <a
                    href="{{ $navigation['primary_cta']['url'] }}"
                    class="site-primary-cta"
                    @if($navigation['primary_cta']['active']) aria-current="page" @endif
                    @if($navigation['primary_cta']['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                >
                    {{ $navigation['primary_cta']['label'] }}
                </a>
            @endif
        </div>

        <details class="site-mobile-navigation">
            <summary>Menu</summary>

            <div class="site-mobile-navigation__panel">
                @if($navigation['items'] !== [])
                    <nav aria-label="Mobile navigation">
                        <x-layouts.navigation-list
                            :items="$navigation['items']"
                            class="site-navigation-list--mobile"
                        />
                    </nav>
                @endif

                @if($navigation['primary_cta'])
                    <a
                        href="{{ $navigation['primary_cta']['url'] }}"
                        class="site-primary-cta"
                        @if($navigation['primary_cta']['active']) aria-current="page" @endif
                        @if($navigation['primary_cta']['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                    >
                        {{ $navigation['primary_cta']['label'] }}
                    </a>
                @endif
            </div>
        </details>
    @endif
</div>