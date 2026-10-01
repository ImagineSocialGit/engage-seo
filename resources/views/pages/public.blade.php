<x-layouts.public :meta="$page['meta']" :site="$site">
    <x-page-breadcrumbs :items="$page['breadcrumbs']" />

    @foreach($page['sections'] as $section)
        <x-dynamic-section :section="$section" />
    @endforeach
</x-layouts.public>