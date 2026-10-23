<?php

use App\Models\FileVersion;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('My uploads')] class extends Component {
    use WithPagination;

    #[Computed]
    public function uploads()
    {
        return Auth::user()->uploads()->with(['zone.expansion'])->latest()->paginate(25);
    }

    #[Computed]
    public function totals(): array
    {
        $uploads = Auth::user()->uploads();

        return [
            'live' => (clone $uploads)->public()->count(),
            'pending' => (clone $uploads)->where('status', FileVersion::STATUS_PENDING)->count(),
            'downloads' => (int) (clone $uploads)->sum('download_count'),
        ];
    }

    public function withdraw(int $id): void
    {
        $file = Auth::user()->uploads()->where('status', FileVersion::STATUS_PENDING)->findOrFail($id);
        $file->forceFill(['status' => FileVersion::STATUS_WITHDRAWN])->save();

        unset($this->uploads, $this->totals);
        Flux::toast(text: "Withdrew version {$file->version} of {$file->zone->name}.");
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">My uploads</flux:heading>
            <flux:subheading>Everything you've shared, and where it stands.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="arrow-up-tray" :href="route('uploads.create')" wire:navigate>Upload a version</flux:button>
    </div>

    <div class="grid grid-cols-3 gap-4">
        @foreach (['Live' => $this->totals['live'], 'Waiting for review' => $this->totals['pending'], 'Downloads' => $this->totals['downloads']] as $label => $value)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="text-sm text-zinc-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
        @forelse ($this->uploads as $file)
            <div wire:key="upload-{{ $file->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                <flux:icon :name="$file->type === 'doors' ? 'key' : 'cube-transparent'" class="size-5 text-zinc-400" />
                <div class="min-w-0 flex-1">
                    <a href="{{ route('zones.show', $file->zone) }}" wire:navigate class="font-medium text-zinc-900 hover:text-accent-content dark:text-white">{{ $file->zone->name }}</a>
                    <div class="text-xs text-zinc-500">{{ $file->typeLabel() }} v{{ $file->version }} &middot; {{ $file->humanSize() }} &middot; {{ $file->created_at->diffForHumans() }}</div>
                    @if ($file->status === 'rejected' && $file->rejection_reason)
                        <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $file->rejection_reason }}</div>
                    @endif
                </div>
                <span class="text-sm tabular-nums text-zinc-500">{{ number_format($file->download_count) }} downloads</span>
                @if ($file->is_official)
                    <flux:badge size="sm" color="emerald" icon="check-badge">Site file</flux:badge>
                @else
                    <flux:badge size="sm" :color="['approved' => 'green', 'pending' => 'amber', 'rejected' => 'red'][$file->status] ?? 'zinc'">{{ ucfirst($file->status) }}</flux:badge>
                @endif
                @if ($file->status === 'pending')
                    <flux:button size="xs" variant="ghost" wire:click="withdraw({{ $file->id }})" wire:confirm="Withdraw this upload?">Withdraw</flux:button>
                @endif
            </div>
        @empty
            <div class="px-6 py-12 text-center text-sm text-zinc-500">
                You haven't uploaded anything yet.
                <a href="{{ route('uploads.create') }}" wire:navigate class="text-accent-content underline">Share your first mesh.</a>
            </div>
        @endforelse
    </div>

    {{ $this->uploads->links() }}
</div>
