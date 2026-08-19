@if($items !== [])
    <div {{ $attributes->class(['section-actions']) }}>
        @foreach($items as $action)
            <a
                href="{{ $action['url'] }}"
                class="section-action"
                data-variant="{{ $action['variant'] }}"
            >
                {{ $action['label'] }}
            </a>
        @endforeach
    </div>
@endif