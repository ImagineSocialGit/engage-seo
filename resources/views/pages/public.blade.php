<x-layouts.public :meta="$page['meta']" :site="$site">
    @foreach($page['sections'] as $section)
        <x-dynamic-section :section="$section" />
    @endforeach
</x-layouts.public>