<x-layouts.public :meta="$page['meta']">
    @foreach($page['sections'] as $section)
        <x-dynamic-section :section="$section" />
    @endforeach
</x-layouts.public>