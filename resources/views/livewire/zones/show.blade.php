<?php

use App\Models\FileVersion;
use App\Models\Report;
use App\Models\Zone;
use App\Services\FileLibrary;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public Zone $zone;

    public ?int $reportingId = null;

    public string $reportReason = '';

    public function mount(Zone $zone): void
    {
        $this->zone = $zone->load('expansion');
    }

    public function rendering($view): void
    {
        $view->title($this->zone->name);
    }

    /**
     * Every version of the zone's files the current user may see, Site file first.
     */
    #[Computed]
    public function files()
    {
        $user = Auth::user();

        return $this->zone->versions()
            ->with(['user', 'reviewer'])
            ->withCount(['reports' => fn ($q) => $q->open()])
            ->siteFirst()
            ->get()
            ->filter(fn (FileVersion $file) => $file->isVisibleTo($user))
            ->groupBy('type');
    }

    public function openReport(int $fileId): void
    {
        abort_unless(Auth::check(), 403);
        $this->reportingId = $fileId;
        $this->reportReason = '';
        $this->modal('report')->show();
    }

    public function submitReport(): void
    {
        abort_unless(Auth::check(), 403);
        $this->validate(['reportReason' => ['required', 'string', 'min:10', 'max:2000']], [], ['reportReason' => 'reason']);

        $file = $this->zone->versions()->findOrFail($this->reportingId);

        Report::create(['file_version_id' => $file->id, 'user_id' => Auth::id(), 'reason' => $this->reportReason]);

        $this->modal('report')->close();
        Flux::toast(variant: 'success', text: 'Thanks. A moderator will take a look.');
    }

    public function makeSiteFile(int $fileId, FileLibrary $library): void
    {
        Gate::authorize('admin');

        $file = $this->zone->versions()->findOrFail($fileId);
        $library->makeSiteFile($file, Auth::user());

        unset($this->files);
        Flux::toast(variant: 'success', text: "Version {$file->version} is now the Site file.");
    }

    public function hide(int $fileId): void
    {
        Gate::authorize('moderate');

        $file = $this->zone->versions()->where('is_official', false)->findOrFail($fileId);
        $file->forceFill([
            'status' => FileVersion::STATUS_REJECTED,
            'rejection_reason' => 'Hidden by a moderator.',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        unset($this->files);
        Flux::toast(text: "Version {$file->version} is hidden.");
    }
}; ?>

<div class="space-y-8">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('zones.index')" wire:navigate>Zones</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('zones.index', ['exp' => [$zone->expansion->code]])" wire:navigate>{{ $zone->expansion->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $zone->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $zone->name }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                <span
                    x-data="{ copied: false }"
                    x-on:click="navigator.clipboard.writeText('{{ $zone->short_name }}'); copied = true; setTimeout(() => copied = false, 1500)"
                    class="cursor-pointer rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                    title="Copy short name"
                ><span x-text="copied ? 'copied!' : '{{ $zone->short_name }}'"></span></span>
                <flux:badge size="sm">{{ $zone->expansion->name }}{{ $zone->expansion->year ? ' · '.$zone->expansion->year : '' }}</flux:badge>
                @if ($zone->eq_zone_id)
                    <span>Zone ID {{ $zone->eq_zone_id }}</span>
                @endif
            </div>
        </div>

        <flux:button variant="primary" icon="arrow-up-tray" :href="route('uploads.create', ['zone' => $zone->short_name])" wire:navigate>
            Upload a version
        </flux:button>
    </div>

    @foreach (config('mqmesh.types') as $type => $meta)
        @php
            $all = $this->files->get($type, collect());
            $site = $all->firstWhere('is_official', true);
            $community = $all->where('is_official', false)->where('status', '!=', 'superseded');
            $history = $all->where('status', 'superseded');
        @endphp

        <section class="space-y-3">
            <div class="flex items-baseline justify-between gap-4">
                <flux:heading size="lg" level="2">{{ $meta['label'] }} <span class="font-mono text-sm font-normal text-zinc-500">{{ sprintf($meta['download_name'], $zone->short_name) }}</span></flux:heading>
                @if ($community->count())
                    <span class="text-sm text-zinc-500">{{ $community->count() }} community {{ Str::plural('version', $community->count()) }}</span>
                @endif
            </div>

            @if (! $site && $community->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    No {{ strtolower($meta['label']) }} file for this zone yet.
                    <a href="{{ route('uploads.create', ['zone' => $zone->short_name, 'type' => $type]) }}" class="text-accent-content underline" wire:navigate>Be the first to upload one.</a>
                </div>
            @endif

            <div class="space-y-3">
                @foreach (collect([$site])->filter()->concat($community) as $file)
                    @include('partials.file-card', ['file' => $file, 'zone' => $zone])
                @endforeach
            </div>

            @if ($history->isNotEmpty())
                <details class="group rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                        <flux:icon name="chevron-right" variant="micro" class="transition group-open:rotate-90" />
                        {{ $history->count() }} older Site {{ Str::plural('file', $history->count()) }}
                    </summary>
                    <div class="divide-y divide-zinc-100 border-t border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($history as $old)
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5 text-sm">
                                <span class="font-medium">v{{ $old->version }}</span>
                                <span class="text-zinc-500">{{ $old->created_at->format('M j, Y') }}</span>
                                <span class="text-zinc-500">{{ $old->humanSize() }}</span>
                                <span class="font-mono text-xs text-zinc-500">{{ $old->md5 }}</span>
                                <flux:spacer />
                                <flux:button size="xs" variant="ghost" icon="arrow-down-tray" :href="route('files.download', $old)">Download</flux:button>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    @endforeach

    <flux:modal name="report" class="md:w-[28rem]">
        <form wire:submit="submitReport" class="space-y-5">
            <div>
                <flux:heading size="lg">Report a problem</flux:heading>
                <flux:subheading>Tell the moderators what's wrong with this file, such as a spot where navigation gets stuck.</flux:subheading>
            </div>
            <flux:textarea wire:model="reportReason" label="What's wrong?" rows="4" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Send report</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
