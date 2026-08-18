@props([
    'meta',
    'site',
])

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $meta['title'] }}</title>

    @if($meta['description'] !== '')
        <meta name="description" content="{{ $meta['description'] }}">
    @endif

    <link rel="canonical" href="{{ $meta['canonical'] }}">
    <meta name="robots" content="{{ $meta['robots'] }}">

    <meta property="og:type" content="{{ $meta['open_graph']['type'] }}">
    <meta property="og:title" content="{{ $meta['open_graph']['title'] }}">

    @if($meta['open_graph']['description'] !== '')
        <meta property="og:description" content="{{ $meta['open_graph']['description'] }}">
    @endif

    <meta property="og:url" content="{{ $meta['open_graph']['url'] }}">

    @if($meta['open_graph']['image'])
        <meta property="og:image" content="{{ $meta['open_graph']['image'] }}">
    @endif

    <meta name="twitter:card" content="{{ $meta['twitter']['card'] }}">
    <meta name="twitter:title" content="{{ $meta['twitter']['title'] }}">

    @if($meta['twitter']['description'] !== '')
        <meta name="twitter:description" content="{{ $meta['twitter']['description'] }}">
    @endif

    @if($meta['twitter']['image'])
        <meta name="twitter:image" content="{{ $meta['twitter']['image'] }}">
    @endif


    @foreach($meta['structured_data'] as $node)
        <script type="application/ld+json">{!! json_encode(
            $node,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
        ) !!}</script>
    @endforeach

    <style>
        :root {
            @foreach($site['theme']['css_variables'] as $name => $value)
                {{ $name }}: {{ $value }};
            @endforeach
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>