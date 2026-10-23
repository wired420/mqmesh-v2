<?php

use App\Models\Expansion;
use App\Models\FileVersion;
use App\Models\Zone;
use App\Support\Bytes;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    /**
     * Send the search to the zone browser.
     */
    public function go(): void
    {
        $this->redirectRoute('zones.index', array_filter(['q' => trim($this->search)]), navigate: true);
    }

    #[Computed]
    public function suggestions()
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return Zone::search($this->search)->with('expansion')->orderBy('name')->limit(6)->get();
    }

    #[Computed]
    public function expansions()
    {
        return Expansion::withCount('zones')->orderBy('sort_order')->get();
    }

    #[Computed]
    public function stats(): array
    {
        $site = FileVersion::where('is_official', true);

        return [
            'zones' => Zone::whereHas('siteMesh')->count(),
            'doors' => (clone $site)->where('type', 'doors')->count(),
            'community' => FileVersion::where('status', FileVersion::STATUS_APPROVED)->where('is_official', false)->count(),
            'size' => Bytes::format((int) (clone $site)->sum('size')),
        ];
    }

    #[Computed]
    public function recent()
    {
        return FileVersion::public()
            ->with(['zone.expansion', 'user'])
            ->latest()
            ->limit(8)
            ->get();
    }
}; ?>

<div class="space-y-14">
    {{-- Hero --}}
    <section class="relative z-10 -mx-4 -mt-8 px-4 pb-6 pt-16 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="pointer-events-none absolute inset-0 overflow-hidden"><div class="mesh-backdrop absolute inset-0"></div></div>

        <div class="relative mx-auto max-w-3xl text-center">
            <flux:badge color="emerald" size="sm" class="mb-5">MQ2Nav meshes for every EverQuest zone</flux:badge>
            <h1 class="text-4xl font-semibold tracking-tight text-zinc-900 sm:text-5xl dark:text-white">
                Find a mesh. Grab it. <span class="text-accent-content">Get moving.</span>
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-base text-zinc-600 dark:text-zinc-400">
                Hand-tuned navigation meshes and door files for {{ number_format($this->stats['zones']) }} zones, plus versions shared by the community.
            </p>

            <form wire:submit="go" class="relative mx-auto mt-8 max-w-xl text-left">
                <flux:input
                    wire:model.live.debounce.200ms="search"
                    icon="magnifying-glass"
                    placeholder="Search zones, like Plane of Knowledge or poknowledge"
                    autofocus
                    autocomplete="off"
                    class="[&_input]:!h-12 [&_input]:!text-base"
                />

                @if ($this->suggestions->isNotEmpty())
                    <div class="absolute inset-x-0 top-full z-20 mt-2 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                        @foreach ($this->suggestions as $zone)
                            <a href="{{ route('zones.show', $zone) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-700/60">
                                <span>
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $zone->name }}</span>
                                    <span class="ml-2 font-mono text-xs text-zinc-500">{{ $zone->short_name }}</span>
                                </span>
                                <span class="text-xs text-zinc-500">{{ $zone->expansion->name }}</span>
                            </a>
                        @endforeach
                        <button type="submit" class="block w-full border-t border-zinc-100 px-4 py-2 text-left text-sm text-accent-content hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/60">
                            See all results for "{{ $search }}" &rarr;
                        </button>
                    </div>
                @endif
            </form>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <flux:button variant="primary" icon="map" :href="route('zones.index')" wire:navigate>Browse all zones</flux:button>
                <flux:button icon="arrow-path" :href="route('updater')" wire:navigate>Get the updater</flux:button>
                <flux:button icon="arrow-up-tray" :href="route('uploads.create')" wire:navigate>Share a mesh</flux:button>
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Zones with meshes', number_format($this->stats['zones']), 'map'],
            ['Door files', number_format($this->stats['doors']), 'key'],
            ['Community versions', number_format($this->stats['community']), 'users'],
            ['Site files total', $this->stats['size'], 'circle-stack'],
        ] as [$label, $value, $icon])
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    <flux:icon :name="$icon" variant="mini" class="size-4" /> {{ $label }}
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</div>
            </div>
        @endforeach
    </section>

    {{-- Expansions --}}
    <section>
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <flux:heading size="lg">Browse by expansion</flux:heading>
                <flux:subheading>Pick one to filter the zone list, then download what you need as one zip.</flux:subheading>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ($this->expansions as $expansion)
                <a
                    href="{{ route('zones.index', ['exp' => [$expansion->code]]) }}"
                    wire:navigate
                    class="group rounded-xl border border-zinc-200 p-4 transition hover:border-accent hover:bg-emerald-50/40 dark:border-zinc-800 dark:hover:bg-emerald-950/20"
                >
                    <div class="flex items-center justify-between text-xs text-zinc-500">
                        <span class="font-mono uppercase">{{ $expansion->code }}</span>
                        <span>{{ $expansion->year }}</span>
                    </div>
                    <div class="mt-2 line-clamp-2 min-h-10 text-sm font-medium leading-5 text-zinc-900 group-hover:text-accent-content dark:text-white">{{ $expansion->name }}</div>
                    <div class="mt-1 text-xs text-zinc-500">{{ $expansion->zones_count }} {{ Str::plural('zone', $expansion->zones_count) }}</div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Recent --}}
    <section>
        <div class="mb-4">
            <flux:heading size="lg">Recently updated</flux:heading>
            <flux:subheading>The newest Site files and approved community versions.</flux:subheading>
        </div>

        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            @forelse ($this->recent as $file)
                <a href="{{ route('zones.show', $file->zone) }}" wire:navigate class="flex items-center gap-4 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <flux:icon :name="$file->type === 'doors' ? 'key' : 'cube-transparent'" class="size-5 shrink-0 text-zinc-400" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $file->zone->name }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $file->typeLabel() }} v{{ $file->version }} &middot; {{ $file->sourceLabel() }}</div>
                    </div>
                    @if ($file->is_official)
                        <flux:badge size="sm" color="emerald" icon="check-badge">Site file</flux:badge>
                    @else
                        <flux:badge size="sm" color="sky">Community</flux:badge>
                    @endif
                    <span class="hidden w-28 text-right text-xs text-zinc-500 sm:block">{{ $file->created_at->diffForHumans() }}</span>
                </a>
            @empty
                <p class="px-4 py-6 text-sm text-zinc-500">Nothing yet.</p>
            @endforelse
        </div>
    </section>
</div>
