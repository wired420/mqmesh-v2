<?php

use App\Models\FileVersion;
use App\Models\Zone;
use App\Services\FileLibrary;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Title('Upload')] class extends Component {
    use WithFileUploads;

    #[Url(as: 'zone')]
    public string $zoneName = '';

    #[Url]
    public string $type = 'mesh';

    public string $zoneSearch = '';

    public $file = null;

    public string $notes = '';

    /**
     * Runs on every request, including the upload itself.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->hasVerifiedEmail(), 403, 'Confirm your email address before uploading.');
    }

    public function mount(): void
    {
        if (! in_array($this->type, ['mesh', 'doors'], true)) {
            $this->type = 'mesh';
        }

        if ($this->zoneName && ! Zone::where('short_name', $this->zoneName)->exists()) {
            $this->zoneName = '';
        }
    }

    #[Computed]
    public function zone(): ?Zone
    {
        return $this->zoneName ? Zone::with(['expansion', 'siteMesh', 'siteDoors'])->firstWhere('short_name', $this->zoneName) : null;
    }

    #[Computed]
    public function matches()
    {
        return mb_strlen(trim($this->zoneSearch)) < 2
            ? collect()
            : Zone::search($this->zoneSearch)->with('expansion')->orderBy('name')->limit(8)->get();
    }

    public function pickZone(string $shortName): void
    {
        $this->zoneName = $shortName;
        $this->zoneSearch = '';
        unset($this->zone);
    }

    /**
     * Guess the zone and type from the file name, like poknowledge.navmesh.
     */
    public function updatedFile(): void
    {
        $this->resetErrorBag('file');
        $name = strtolower($this->file?->getClientOriginalName() ?? '');

        if (str_ends_with($name, '_doors.json')) {
            $this->type = 'doors';
            $guess = substr($name, 0, -strlen('_doors.json'));
        } elseif (str_ends_with($name, '.navmesh')) {
            $this->type = 'mesh';
            $guess = substr($name, 0, -strlen('.navmesh'));
        } else {
            return;
        }

        if (! $this->zoneName && Zone::where('short_name', $guess)->exists()) {
            $this->pickZone($guess);
        }
    }

    public function save(FileLibrary $library): void
    {
        $user = Auth::user();

        $this->validate([
            'zoneName' => ['required', 'exists:zones,short_name'],
            'type' => ['required', 'in:mesh,doors'],
            'file' => ['required', 'file', 'max:'.config('mqmesh.max_upload_kb')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['zoneName.required' => 'Pick the zone this file is for.'], ['zoneName' => 'zone']);

        if (RateLimiter::tooManyAttempts('upload:'.$user->id, 60)) {
            $this->addError('file', 'You have uploaded a lot in the last hour. Please try again later.');

            return;
        }

        $path = $this->file->getRealPath();

        if ($problem = $library->problemWith($this->type, $path)) {
            $this->addError('file', $problem);

            return;
        }

        $zone = $this->zone;
        $duplicate = $zone->versions()->where('type', $this->type)->where('sha256', hash_file('sha256', $path))
            ->whereNotIn('status', [FileVersion::STATUS_REJECTED, FileVersion::STATUS_WITHDRAWN])->first();

        if ($duplicate) {
            $this->addError('file', "This exact file is already version {$duplicate->version} of this zone.");

            return;
        }

        RateLimiter::hit('upload:'.$user->id, 3600);

        $version = $library->store($zone, $this->type, $path, [
            'source' => FileVersion::SOURCE_UPLOAD,
            'status' => $user->skipsReview() ? FileVersion::STATUS_APPROVED : FileVersion::STATUS_PENDING,
            'user' => $user,
            'notes' => trim($this->notes) ?: null,
            'original_name' => $this->file->getClientOriginalName(),
        ]);

        $this->file->delete();

        Flux::toast(
            variant: 'success',
            heading: 'Upload received',
            text: $version->status === FileVersion::STATUS_APPROVED
                ? "Version {$version->version} is live on {$zone->name}."
                : "Version {$version->version} is waiting for a moderator.",
        );

        $this->redirectRoute('zones.show', $zone, navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">Upload a version</flux:heading>
        <flux:subheading>
            Share your own mesh or doors file for any zone. It shows up under the zone's Site file
            @if (auth()->user()->skipsReview()) and goes live straight away. @else once a moderator approves it. @endif
        </flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- File drop --}}
        <flux:field>
            <flux:label>File</flux:label>
            <label
                x-data="{ over: false, progress: 0, uploading: false }"
                x-on:dragover.prevent="over = true"
                x-on:dragleave.prevent="over = false"
                x-on:drop.prevent="over = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change'))"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                :class="over ? 'border-accent bg-emerald-50/50 dark:bg-emerald-950/30' : 'border-zinc-300 dark:border-zinc-700'"
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition"
            >
                <input x-ref="input" type="file" wire:model="file" class="sr-only" accept=".navmesh,.json,application/json,application/octet-stream" />

                <template x-if="uploading">
                    <div class="w-full max-w-xs space-y-2">
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">Uploading… <span x-text="progress + '%'"></span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-accent transition-all" :style="`width: ${progress}%`"></div>
                        </div>
                    </div>
                </template>

                <template x-if="! uploading">
                    <div class="space-y-1">
                        @if ($file)
                            <flux:icon name="document-check" class="mx-auto size-8 text-accent-content" />
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $file->getClientOriginalName() }}</div>
                            <div class="text-xs text-zinc-500">{{ \App\Support\Bytes::format($file->getSize()) }} &middot; click or drop to replace</div>
                        @else
                            <flux:icon name="arrow-up-tray" class="mx-auto size-8 text-zinc-400" />
                            <div class="font-medium text-zinc-900 dark:text-white">Drop a <span class="font-mono">.navmesh</span> or <span class="font-mono">_doors.json</span> file here</div>
                            <div class="text-xs text-zinc-500">or click to choose one. Up to {{ \App\Support\Bytes::format(config('mqmesh.max_upload_kb') * 1024, 0) }}.</div>
                        @endif
                    </div>
                </template>
            </label>
            <flux:error name="file" />
        </flux:field>

        {{-- Zone picker --}}
        <flux:field>
            <flux:label>Zone</flux:label>
            @if ($this->zone)
                <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2.5 dark:border-zinc-700">
                    <div>
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $this->zone->name }}</span>
                        <span class="ml-2 font-mono text-xs text-zinc-500">{{ $this->zone->short_name }}</span>
                        <div class="text-xs text-zinc-500">{{ $this->zone->expansion->name }}</div>
                    </div>
                    <flux:button size="sm" variant="ghost" wire:click="$set('zoneName', '')">Change</flux:button>
                </div>
            @else
                <div class="relative">
                    <flux:input wire:model.live.debounce.200ms="zoneSearch" icon="magnifying-glass" placeholder="Search for a zone" autocomplete="off" />
                    @if ($this->matches->isNotEmpty())
                        <div class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                            @foreach ($this->matches as $match)
                                <button type="button" wire:click="pickZone('{{ $match->short_name }}')" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700/60">
                                    <span>
                                        <span class="font-medium">{{ $match->name }}</span>
                                        <span class="ml-2 font-mono text-xs text-zinc-500">{{ $match->short_name }}</span>
                                    </span>
                                    <span class="text-xs text-zinc-500">{{ $match->expansion->code }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            <flux:error name="zoneName" />
        </flux:field>

        <flux:radio.group wire:model.live="type" label="File type" variant="segmented">
            <flux:radio value="mesh" label="Mesh (.navmesh)" />
            <flux:radio value="doors" label="Doors (_doors.json)" />
        </flux:radio.group>

        <flux:textarea wire:model="notes" label="Notes" description="What did you change? For example: fixed the water near the docks, added the upper ledges." rows="3" />

        @if ($this->zone && ($current = $this->zone->siteFile($type)))
            <flux:callout icon="information-circle" variant="secondary">
                <flux:callout.text>
                    The current Site file is version {{ $current->version }} ({{ $current->humanSize() }}, {{ $current->created_at->format('M j, Y') }}). Yours will be listed below it as a community version.
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" :href="url()->previous()">Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="file,save">Upload</flux:button>
        </div>
    </form>
</div>
