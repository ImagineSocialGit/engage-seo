@props([
    'section',
])

@php
    $view = app(\App\Support\Sections\SectionManager::class)
        ->viewFor($section['component']);
@endphp

@include($view, [
    ...$section['props'],
    'id' => $section['id'],
    'theme' => $section['theme'],
    'layout' => $section['layout'],
    'overrides' => $section['overrides'],
])