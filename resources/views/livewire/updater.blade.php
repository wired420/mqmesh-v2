<?php

use App\Support\UpdaterReleases;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Updater')] class extends Component {
    #[Computed]
    public function releases()
    {
        return UpdaterReleases::latest();
    }

    /**
     * Show the new builds right after an admin publishes them.
     */
    #[On('updater-published')]
    public function refreshReleases(): void
    {
        unset($this->releases);
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-10">
    <div>
        <flux:heading size="xl" level="1">MQMesh Updater</flux:heading>
        <flux:subheading>
            Keeps the meshes in your MacroQuest folder in step with this site. It downloads only what changed,
            checks every file against its hash, and lets you pick a community version for any zone.
        </flux:subheading>
    </div>

    @if ($this->releases->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center text-sm text-zinc-500 dark:border-zinc-700">
            No builds are up yet. Check back soon.
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($this->releases as $release)
                <div wire:key="release-{{ $release['platform'] }}" class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-accent-content dark:bg-emerald-950/40">
                            <flux:icon :name="$release['icon']" />
                        </div>
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $release['label'] }}</div>
                            <div class="text-xs text-zinc-500">Version {{ $release['version'] }} &middot; {{ $release['arch'] }} &middot; {{ \App\Support\Bytes::format($release['size']) }} &middot; {{ $release['date']->format('M j, Y') }}</div>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $release['needs'] }}</p>

                    <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-zinc-700 dark:text-zinc-300">
                        @foreach ($release['steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>

                    <div class="mt-auto space-y-3 pt-5">
                        <flux:button variant="primary" icon="arrow-down-tray" class="w-full" :href="route('updater.download', $release['file'])">
                            Download for {{ $release['label'] }}
                        </flux:button>
                        <div x-data="{ copied: false }" class="font-mono text-[11px] text-zinc-500">
                            <button type="button" class="w-full truncate text-left hover:text-zinc-800 dark:hover:text-zinc-200" title="Copy SHA-256"
                                x-on:click="navigator.clipboard.writeText('{{ $release['sha256'] }}'); copied = true; setTimeout(() => copied = false, 1500)">
                                SHA-256 <span x-text="copied ? 'copied!' : '{{ $release['sha256'] }}'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <figure class="overflow-hidden rounded-xl border border-zinc-200 shadow-sm dark:border-zinc-800">
        <a href="{{ asset('images/updater-windows.png') }}" target="_blank" rel="noopener" title="Open full size">
            <img src="{{ asset('images/updater-windows.png') }}" width="1086" height="753" loading="lazy"
                alt="The MQMesh Updater on Windows: zones grouped by expansion with search, status, size, a mesh choice per zone and a doors checkbox"
                class="block h-auto w-full" />
        </a>
        <figcaption class="border-t border-zinc-200 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-800">
            The Windows version. Zones are grouped by expansion; pick the Site mesh, a community version or "don't manage" for each one.
        </figcaption>
    </figure>

    <div class="space-y-3">
        <flux:heading size="lg" level="2">Run it on a schedule</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Both versions have a command-line mode that applies your saved choices without opening a window, for Task Scheduler, cron or a systemd timer.
        </p>
        <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 font-mono text-xs leading-relaxed text-zinc-100"><code>MQMeshUpdater.exe --update [--mq &lt;folder&gt;] [--quiet]     # Windows
./mqmesh-updater --update [--mq &lt;folder&gt;] [--quiet]      # Linux</code></pre>
    </div>

    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        Source, release notes and bug reports:
        <a href="https://github.com/wired420/MQMeshUpdaterv2" class="text-accent-content underline" target="_blank" rel="noopener">github.com/wired420/MQMeshUpdaterv2</a>
    </p>

    @can('admin')
        <livewire:updater-publish />
    @endcan
</div>
