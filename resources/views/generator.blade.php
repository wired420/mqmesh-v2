@php
    // Pass a mesh to open (from the moderation pages) through to the app.
    $view = array_filter(request()->only(['zone', 'mesh', 'label']), 'is_string');
    $src = asset('generator-app/index.html').($view ? '?'.http_build_query($view) : '');
@endphp
<x-layouts.app title="Mesh generator" :full-width="true">
    <iframe
        src="{{ $src }}"
        title="MQ2Nav mesh generator"
        allow="cross-origin-isolated; clipboard-read; clipboard-write; fullscreen"
        class="block min-h-0 w-full flex-1 border-0 bg-zinc-900"
    ></iframe>
</x-layouts.app>
