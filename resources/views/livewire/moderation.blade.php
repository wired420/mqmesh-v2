<?php

use App\Models\FileVersion;
use App\Models\Report;
use App\Services\FileLibrary;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Moderation')] class extends Component {
    #[Url]
    public string $tab = 'uploads';

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    /**
     * Runs on every request, including each button press.
     */
    public function boot(): void
    {
        Gate::authorize('moderate');
    }

    #[Computed]
    public function pending()
    {
        return FileVersion::where('status', FileVersion::STATUS_PENDING)
            ->with(['zone.expansion', 'zone.siteMesh', 'zone.siteDoors', 'user'])
            ->oldest()
            ->get();
    }

    #[Computed]
    public function reports()
    {
        return Report::open()->with(['fileVersion.zone', 'fileVersion.user', 'user'])->oldest()->get();
    }

    public function approve(int $id, bool $makeSite = false): void
    {
        $file = FileVersion::where('status', FileVersion::STATUS_PENDING)->findOrFail($id);

        $file->forceFill([
            'status' => FileVersion::STATUS_APPROVED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        if ($makeSite) {
            Gate::authorize('admin');
            app(FileLibrary::class)->makeSiteFile($file, Auth::user());
        }

        unset($this->pending);
        Flux::toast(variant: 'success', text: "Approved {$file->zone->name} v{$file->version}".($makeSite ? ' as the Site file.' : '.'));
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
        $this->modal('reject')->show();
    }

    public function reject(): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'max:1000']], [], ['rejectReason' => 'reason']);

        $file = FileVersion::where('status', FileVersion::STATUS_PENDING)->findOrFail($this->rejectingId);
        $file->forceFill([
            'status' => FileVersion::STATUS_REJECTED,
            'rejection_reason' => $this->rejectReason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        $this->modal('reject')->close();
        unset($this->pending);
        Flux::toast(text: "Rejected {$file->zone->name} v{$file->version}.");
    }

    public function resolve(int $id, bool $hideFile = false): void
    {
        $report = Report::open()->with('fileVersion')->findOrFail($id);

        if ($hideFile && ! $report->fileVersion->is_official) {
            $report->fileVersion->forceFill([
                'status' => FileVersion::STATUS_REJECTED,
                'rejection_reason' => 'Hidden after a report: '.$report->reason,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ])->save();
        }

        $report->forceFill(['resolved_by' => Auth::id(), 'resolved_at' => now()])->save();

        unset($this->reports);
        Flux::toast(text: $hideFile ? 'File hidden and report closed.' : 'Report closed.');
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">Moderation</flux:heading>
        <flux:subheading>Review uploads before they go live, and follow up on reported files.</flux:subheading>
    </div>

    <flux:navbar class="border-b border-zinc-200 dark:border-zinc-800">
        <flux:navbar.item wire:click="$set('tab', 'uploads')" :current="$tab === 'uploads'" :badge="$this->pending->count() ?: null" badge-color="amber">Pending uploads</flux:navbar.item>
        <flux:navbar.item wire:click="$set('tab', 'reports')" :current="$tab === 'reports'" :badge="$this->reports->count() ?: null" badge-color="red">Reports</flux:navbar.item>
    </flux:navbar>

    @if ($tab === 'uploads')
        <div class="space-y-3">
            @forelse ($this->pending as $file)
                @php $site = $file->zone->siteFile($file->type); @endphp
                <div wire:key="pending-{{ $file->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('zones.show', $file->zone) }}" wire:navigate class="font-semibold text-zinc-900 hover:text-accent-content dark:text-white">{{ $file->zone->name }}</a>
                                <span class="font-mono text-xs text-zinc-500">{{ $file->downloadName() }}</span>
                                <flux:badge size="sm">{{ $file->typeLabel() }} v{{ $file->version }}</flux:badge>
                            </div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                From <span class="font-medium">{{ $file->user?->name ?? 'deleted user' }}</span>
                                &middot; {{ $file->created_at->diffForHumans() }}
                                &middot; {{ $file->humanSize() }}
                                @if ($site)
                                    @php $diff = $file->size - $site->size; @endphp
                                    <span @class(['text-emerald-600' => $diff > 0, 'text-amber-600' => $diff < 0])>
                                        ({{ $diff >= 0 ? '+' : '−' }}{{ \App\Support\Bytes::format(abs($diff)) }} vs Site file v{{ $site->version }})
                                    </span>
                                @else
                                    <span class="text-sky-600">(no Site file yet)</span>
                                @endif
                            </div>
                            @if ($file->original_name)
                                <div class="font-mono text-xs text-zinc-500">uploaded as {{ $file->original_name }}</div>
                            @endif
                            @if ($file->notes)
                                <p class="whitespace-pre-line pt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $file->notes }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('files.download', $file)">Download</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="startReject({{ $file->id }})">Reject</flux:button>
                            @if (auth()->user()->isAdmin())
                                <flux:button size="sm" wire:click="approve({{ $file->id }}, true)" wire:confirm="Approve and replace the Site file?">Approve as Site file</flux:button>
                            @endif
                            <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $file->id }})">Approve</flux:button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    Nothing waiting. Nice.
                </div>
            @endforelse
        </div>
    @else
        <div class="space-y-3">
            @forelse ($this->reports as $report)
                @php $file = $report->fileVersion; @endphp
                <div wire:key="report-{{ $report->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('zones.show', $file->zone) }}" wire:navigate class="font-semibold text-zinc-900 hover:text-accent-content dark:text-white">{{ $file->zone->name }}</a>
                                <flux:badge size="sm">{{ $file->typeLabel() }} v{{ $file->version }}</flux:badge>
                                @if ($file->is_official)
                                    <flux:badge size="sm" color="emerald">Site file</flux:badge>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-500">Reported by {{ $report->user->name }} &middot; {{ $report->created_at->diffForHumans() }}</div>
                            <p class="whitespace-pre-line pt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $report->reason }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('files.download', $file)">Download</flux:button>
                            @unless ($file->is_official)
                                <flux:button size="sm" variant="danger" wire:click="resolve({{ $report->id }}, true)" wire:confirm="Hide this file and close the report?">Hide file</flux:button>
                            @endunless
                            <flux:button size="sm" wire:click="resolve({{ $report->id }})">Close report</flux:button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    No open reports.
                </div>
            @endforelse
        </div>
    @endif

    <flux:modal name="reject" class="md:w-[28rem]">
        <form wire:submit="reject" class="space-y-5">
            <div>
                <flux:heading size="lg">Reject upload</flux:heading>
                <flux:subheading>The uploader sees this reason on their uploads page.</flux:subheading>
            </div>
            <flux:textarea wire:model="rejectReason" label="Reason" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Reject</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
