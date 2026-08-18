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
            <header class="site-header">
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
            <footer class="site-footer">
                @isset($footer)
                    {{ $footer }}
                @else
                    <x-layouts.site-footer :site="$site" />
                @endisset
            </footer>
        @endif
    </body>
</html>