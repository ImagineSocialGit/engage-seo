@props([
    'meta',
    'site',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <x-layouts.head :meta="$meta" :site="$site" />

    <body class="site-body">
        <a href="#main-content" class="sr-only focus:not-sr-only">
            Skip to content
        </a>

        @if($site['shell']['header']['enabled'])
            <header
                class="site-header shell-region"
                data-shell-theme="{{ $site['shell']['header']['theme'] }}"
            >
                @if(
                    $site['shell']['utility_bar']['enabled']
                    && $site['shell']['utility_bar']['items'] !== []
                )
                    <x-layouts.site-utility-bar :site="$site" />
                @endif

                @isset($header)
                    {{ $header }}
                @else
                    <x-layouts.site-header :site="$site" />
                @endisset
            </header>
        @endif

        <main id="main-content" tabindex="-1">
            {{ $slot }}
        </main>

        @if($site['shell']['footer']['enabled'])
            <footer
                class="site-footer shell-region"
                data-shell-theme="{{ $site['shell']['footer']['theme'] }}"
            >
                @isset($footer)
                    {{ $footer }}
                @else
                    <x-layouts.site-footer :site="$site" />
                @endisset
            </footer>
        @endif
    </body>
</html>