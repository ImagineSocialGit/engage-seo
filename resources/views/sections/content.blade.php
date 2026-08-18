@php
    $title = $title ?? null;
    $content = $content ?? null;
@endphp

<section @if($id) id="{{ $id }}" @endif>
    @if(is_string($title) && trim($title) !== '')
        <h2>{{ $title }}</h2>
    @endif

    @if(is_array($content))
        @foreach($content as $paragraph)
            @if(is_string($paragraph) && trim($paragraph) !== '')
                <p>{{ $paragraph }}</p>
            @endif
        @endforeach
    @elseif(is_string($content) && trim($content) !== '')
        <p>{{ $content }}</p>
    @endif
</section>