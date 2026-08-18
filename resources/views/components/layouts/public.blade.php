@props([
    'meta',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <x-layouts.head :meta="$meta" />

    <body>
        <a href="#main-content" class="sr-only focus:not-sr-only">
            Skip to content
        </a>

        @isset($header)
            <header>
                {{ $header }}
            </header>
        @endisset

        <main id="main-content">
            {{ $slot }}
        </main>

        @isset($footer)
            <footer>
                {{ $footer }}
            </footer>
        @endisset
    </body>
</html>