@php
    $base = url('/api/v1');
    $endpoints = [
        ['GET', '/manifest', 'Every zone\'s Site files: names, sizes, MD5 and SHA-256 hashes and download links. Send the ETag back in If-None-Match and you get 304 Not Modified when nothing changed. Add ?since=2026-01-01T00:00:00Z for only the zones changed since then.', 'Public'],
        ['GET', '/expansions', 'Expansion codes, names, release years and zone counts, in release order.', 'Public'],
        ['GET', '/zones', 'Zones with the same filters as the zone browser: q, exp[]=kunark, doors=1, community=1, updated=30d|90d|year, sort, dir, per_page (up to 500).', 'Public'],
        ['GET', '/zones/{short_name}', 'One zone with its Site files and every approved community version.', 'Public'],
        ['GET', '/files/{id}/download', 'Download one file, named the way MQ2Nav expects.', 'Public'],
        ['GET', '/zip?zones=a,b,c', 'A zip of those zones\' Site meshes, built as it downloads. Add with=doors for doors files, or pass the /zones filters instead of zones=.', 'Public, 6 per minute'],
        ['POST', '/zones/{short_name}/versions', 'Upload a community version. Multipart fields: type (mesh or doors), file, notes. Returns 201 with the new version, 409 if the same file already exists, 422 if it is not a valid file.', 'Token with upload'],
    ];
@endphp

<x-layouts.app title="API">
    <div class="mx-auto max-w-4xl space-y-10">
        <div>
            <flux:heading size="xl" level="1">API</flux:heading>
            <flux:subheading>A small JSON API for mesh updaters and other tools. Everything except uploading works without an account.</flux:subheading>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg" level="2">Base URL</flux:heading>
            <code class="block rounded-lg bg-zinc-100 px-4 py-3 font-mono text-sm dark:bg-zinc-800">{{ $base }}</code>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Public calls are limited to 60 a minute per IP address. For uploads, create a token under
                <a href="{{ route('settings.tokens') }}" class="text-accent-content underline">Settings, API tokens</a>
                and send it as <span class="font-mono">Authorization: Bearer &lt;token&gt;</span>.
            </p>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg" level="2">Endpoints</flux:heading>
            <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                @foreach ($endpoints as [$method, $path, $description, $auth])
                    <div class="grid gap-2 px-4 py-4 sm:grid-cols-[17rem_1fr]">
                        <div class="font-mono text-sm">
                            <span @class(['mr-2 rounded px-1.5 py-0.5 text-xs font-semibold', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300' => $method === 'GET', 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300' => $method === 'POST'])>{{ $method }}</span>{{ $path }}
                        </div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300">
                            {{ $description }}
                            <div class="mt-1 text-xs text-zinc-500">{{ $auth }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg" level="2">Checking for updates</flux:heading>
            <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 font-mono text-xs leading-relaxed text-zinc-100"><code># First run: save the ETag
curl -si {{ $base }}/manifest | grep -i etag

# Later runs: only download the manifest when something changed
curl -s -H 'If-None-Match: "&lt;etag&gt;"' -o manifest.json -w '%{http_code}' {{ $base }}/manifest</code></pre>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Compare each zone's <span class="font-mono">md5</span> with your local file and download only the ones that differ.</p>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg" level="2">Example zone</flux:heading>
            <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 font-mono text-xs leading-relaxed text-zinc-100"><code>{
  "short_name": "poknowledge",
  "name": "The Plane of Knowledge",
  "eq_zone_id": 202,
  "expansion": "pop",
  "updated_at": "2026-08-13T21:04:11+00:00",
  "url": "{{ url('zones/poknowledge') }}",
  "files": {
    "mesh": {
      "id": 412, "type": "mesh", "version": 3, "site_file": true,
      "file_name": "poknowledge.navmesh", "size": 8700850,
      "md5": "…", "sha256": "…",
      "created_at": "2026-08-13T21:04:11+00:00",
      "download_url": "{{ $base }}/files/412/download"
    }
  }
}</code></pre>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg" level="2">Old updater files</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                <a href="{{ url('zones.json') }}" class="font-mono text-accent-content underline">zones.json</a>,
                <a href="{{ url('updater.json') }}" class="font-mono text-accent-content underline">updater.json</a> and
                <a href="{{ url('meshdb.json') }}" class="font-mono text-accent-content underline">meshdb.json</a>
                are still published in their original formats and kept current with the Site files, so existing updaters keep working.
            </p>
        </div>
    </div>
</x-layouts.app>
