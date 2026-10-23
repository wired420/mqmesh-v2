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
        {{-- Each card ends with a screenshot thumbnail that opens full size in a lightbox --}}
        <div class="grid gap-4 md:grid-cols-2" x-data="{ shot: null, label: '' }" x-on:keydown.escape.window="shot = null">
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

                        @if (! empty($release['screenshot']))
                            <button
                                type="button"
                                x-on:click="shot = '{{ asset($release['screenshot']) }}'; label = '{{ $release['label'] }}'"
                                class="group relative block w-full overflow-hidden rounded-lg border border-zinc-200 transition hover:border-accent dark:border-zinc-800"
                                aria-label="View the {{ $release['label'] }} screenshot full size"
                            >
                                <img src="{{ asset($release['screenshot']) }}" width="1086" height="753" loading="lazy"
                                    alt="The MQMesh Updater on {{ $release['label'] }}"
                                    class="block h-auto w-full" />
                                <span class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition group-hover:bg-black/40 group-hover:opacity-100">
                                    <flux:icon name="magnifying-glass-plus" class="size-7 text-white" />
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach

            <template x-teleport="body">
                <div
                    x-show="shot"
                    x-transition.opacity
                    x-on:click="shot = null"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="label + ' screenshot'"
                    style="display: none"
                >
                    <img :src="shot" :alt="'The MQMesh Updater on ' + label + ', full size'" x-on:click.stop
                        class="max-h-[90vh] w-auto max-w-full rounded-lg shadow-2xl" />
                    <button type="button" x-on:click="shot = null" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Close">
                        <flux:icon name="x-mark" class="size-6" />
                    </button>
                </div>
            </template>
        </div>
    @endif

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
