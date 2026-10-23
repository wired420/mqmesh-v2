<?php

use App\Models\Expansion;
use App\Support\ZoneFilters;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Zones')] class extends Component {
    use WithPagination;

    #[Url]
    public string $q = '';

    /** @var list<string> */
    #[Url]
    public array $exp = [];

    #[Url]
    public bool $doors = false;

    #[Url]
    public bool $community = false;

    #[Url]
    public string $updated = '';

    #[Url]
    public string $sort = 'name';

    #[Url]
    public string $dir = 'asc';

    /** Zone short names ticked for a zip download. @var list<string> */
    public array $selected = [];

    public bool $withDoors = true;

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'exp', 'doors', 'community', 'updated'], true) || str_starts_with($property, 'exp.')) {
            $this->resetPage();
        }
    }

    public function toggleExpansion(string $code): void
    {
        $this->exp = in_array($code, $this->exp, true)
            ? array_values(array_diff($this->exp, [$code]))
            : [...$this->exp, $code];

        $this->resetPage();
    }

    /**
     * Select a named group of expansions.
     */
    public function quickGroup(string $group): void
    {
        $codes = Expansion::where('code', '!=', 'other')->orderBy('sort_order')->pluck('code');

        $this->exp = match ($group) {
            'classic-pop' => $codes->take(5)->values()->all(),
            'latest' => $codes->take(-3)->values()->all(),
            default => [],
        };

        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $this->dir = $this->sort === $column && $this->dir === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'exp', 'doors', 'community', 'updated');
        $this->resetPage();
    }

    /**
     * Tick or untick every zone on the current page.
     */
    public function togglePage(): void
    {
        $page = $this->zones->pluck('short_name')->all();

        $this->selected = array_diff($page, $this->selected)
            ? array_values(array_unique([...$this->selected, ...$page]))
            : array_values(array_diff($this->selected, $page));
    }

    public function filters(): ZoneFilters
    {
        return new ZoneFilters($this->q, $this->exp, $this->doors, $this->community, $this->updated, $this->sort, $this->dir);
    }

    #[Computed]
    public function zones()
    {
        return $this->filters()->query()
            ->with(['expansion', 'siteMesh', 'siteDoors'])
            ->withCount('communityVersions')
            ->paginate(50);
    }

    #[Computed]
    public function expansions()
    {
        return Expansion::withCount('zones')->orderBy('sort_order')->get();
    }

    /**
     * Total size of every Site mesh and doors file, for the "download all" button.
     */
    public function everythingSize(): int
    {
        return (int) \App\Models\FileVersion::where('is_official', true)->sum('size');
    }

    public function filtering(): bool
    {
        return $this->q !== '' || $this->exp || $this->doors || $this->community || $this->updated !== '';
    }

    public function selectedZipUrl(): string
    {
        return route('downloads.zip', array_filter(['zones' => implode(',', $this->selected), 'with' => $this->withDoors ? 'doors' : null]));
    }

    public function matchingZipUrl(): string
    {
        return route('downloads.zip', array_filter([...$this->filters()->toQuery(), 'with' => $this->withDoors ? 'doors' : null]));
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Zones</flux:heading>
            <flux:subheading>
                {{ number_format($this->zones->total()) }} {{ Str::plural('zone', $this->zones->total()) }}
                @if ($this->filtering()) match your filters @else across {{ $this->expansions->count() }} expansions @endif
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <flux:switch wire:model.live="withDoors" label="Include doors files" align="left" />
            <flux:button variant="primary" icon="archive-box-arrow-down" :href="route('downloads.zip', ['with' => 'doors'])">
                Download all meshes and doors
                <span class="ml-1 text-xs font-normal opacity-80">({{ \App\Support\Bytes::format($this->everythingSize()) }})</span>
            </flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
        <div class="grid gap-3 md:grid-cols-[1fr_auto_auto_auto]">
            <flux:input wire:model.live.debounce.250ms="q" icon="magnifying-glass" placeholder="Search by name or short name" clearable />

            <flux:select wire:model.live="updated" class="md:w-44">
                <flux:select.option value="">Any time</flux:select.option>
                @foreach (\App\Support\ZoneFilters::UPDATED as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-4 md:px-2">
                <flux:checkbox wire:model.live="doors" label="Has doors" />
                <flux:checkbox wire:model.live="community" label="Has community versions" />
            </div>

            @if ($this->filtering())
                <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">Clear</flux:button>
            @endif
        </div>

        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                <span class="font-medium uppercase tracking-wide">Expansions</span>
                <span class="text-zinc-300 dark:text-zinc-700">|</span>
                <button type="button" wire:click="quickGroup('classic-pop')" class="hover:text-accent-content">Classic to PoP</button>
                <button type="button" wire:click="quickGroup('latest')" class="hover:text-accent-content">Latest 3</button>
                @if ($exp)
                    <button type="button" wire:click="quickGroup('none')" class="hover:text-accent-content">All</button>
                @endif
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($this->expansions as $expansion)
                    @php $on = in_array($expansion->code, $exp, true); @endphp
                    <button
                        type="button"
                        wire:click="toggleExpansion('{{ $expansion->code }}')"
                        title="{{ $expansion->name }}{{ $expansion->year ? ' ('.$expansion->year.')' : '' }}"
                        @class([
                            'rounded-full border px-2.5 py-1 text-xs font-medium transition',
                            'border-accent bg-accent text-accent-foreground' => $on,
                            'border-zinc-200 text-zinc-600 hover:border-zinc-400 dark:border-zinc-700 dark:text-zinc-300' => ! $on,
                        ])
                    >
                        <span class="uppercase">{{ $expansion->code }}</span>
                        <span @class(['ml-1 tabular-nums', 'opacity-70' => $on, 'text-zinc-400' => ! $on])>{{ $expansion->zones_count }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Download bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-800/60">
        <div class="text-zinc-600 dark:text-zinc-300">
            @if ($selected)
                <span class="font-medium text-zinc-900 dark:text-white">{{ count($selected) }}</span> {{ Str::plural('zone', count($selected)) }} selected
                <button type="button" wire:click="$set('selected', [])" class="ml-2 text-xs text-zinc-500 underline">clear</button>
            @else
                Tick zones to build your own zip, or grab everything that matches.
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($selected)
                <flux:button size="sm" variant="primary" icon="arrow-down-tray" :href="$this->selectedZipUrl()">Download {{ count($selected) }} as zip</flux:button>
            @endif
            <flux:button size="sm" icon="archive-box-arrow-down" :href="$this->matchingZipUrl()">
                Download all {{ number_format($this->zones->total()) }} {{ $this->filtering() ? 'matching' : '' }}
            </flux:button>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="w-10 px-4 py-3">
                        <flux:checkbox wire:click="togglePage" :checked="$this->zones->isNotEmpty() && ! array_diff($this->zones->pluck('short_name')->all(), $selected)" aria-label="Select this page" />
                    </th>
                    @foreach (['name' => 'Zone', 'expansion' => 'Expansion', 'size' => 'Mesh', 'updated' => 'Updated', 'downloads' => 'Downloads'] as $column => $label)
                        <th @class(['px-4 py-3 font-medium', 'hidden md:table-cell' => in_array($column, ['updated', 'downloads'])])>
                            <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex items-center gap-1 uppercase hover:text-zinc-900 dark:hover:text-white">
                                {{ $label }}
                                @if ($sort === $column)
                                    <flux:icon :name="$dir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-right font-medium">Files</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800" wire:loading.class="opacity-60">
                @forelse ($this->zones as $zone)
                    <tr wire:key="zone-{{ $zone->id }}" class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                        <td class="px-4 py-3">
                            <flux:checkbox wire:model.live="selected" :value="$zone->short_name" aria-label="Select {{ $zone->name }}" />
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('zones.show', $zone) }}" wire:navigate class="font-medium text-zinc-900 hover:text-accent-content dark:text-white">{{ $zone->name }}</a>
                            <div class="font-mono text-xs text-zinc-500">{{ $zone->short_name }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-zinc-700 dark:text-zinc-300" title="{{ $zone->expansion->name }}">{{ $zone->expansion->name }}</span>
                        </td>
                        <td class="px-4 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">
                            {{ $zone->siteMesh?->humanSize() ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-3 text-zinc-600 md:table-cell dark:text-zinc-400" title="{{ $zone->updated_at }}">
                            {{ $zone->siteMesh ? $zone->updated_at->format('M j, Y') : '—' }}
                        </td>
                        <td class="hidden px-4 py-3 tabular-nums text-zinc-600 md:table-cell dark:text-zinc-400">
                            {{ number_format(($zone->siteMesh?->download_count ?? 0) + ($zone->siteDoors?->download_count ?? 0)) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($zone->community_versions_count)
                                    <flux:tooltip content="{{ $zone->community_versions_count }} community {{ Str::plural('version', $zone->community_versions_count) }}">
                                        <flux:badge size="sm" color="sky" :href="route('zones.show', $zone)">+{{ $zone->community_versions_count }}</flux:badge>
                                    </flux:tooltip>
                                @endif
                                @if ($zone->siteDoors)
                                    <flux:tooltip content="Download doors file">
                                        <flux:button size="xs" variant="ghost" icon="key" :href="route('files.download', $zone->siteDoors)" aria-label="Download doors file" />
                                    </flux:tooltip>
                                @endif
                                @if ($zone->siteMesh)
                                    <flux:button size="xs" icon="arrow-down-tray" :href="route('files.download', $zone->siteMesh)">Mesh</flux:button>
                                @else
                                    <flux:button size="xs" variant="ghost" icon="arrow-up-tray" :href="route('uploads.create', ['zone' => $zone->short_name])" wire:navigate>Add</flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-zinc-500">
                            No zones match. <button type="button" wire:click="clearFilters" class="text-accent-content underline">Clear the filters</button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->zones->links() }}
</div>
