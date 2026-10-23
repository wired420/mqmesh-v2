@php
    $user = auth()->user();
    $statusColor = ['pending' => 'amber', 'rejected' => 'red', 'withdrawn' => 'zinc'][$file->status] ?? null;
@endphp

<div wire:key="file-{{ $file->id }}" @class([
    'rounded-xl border p-4 sm:p-5',
    'border-emerald-500/50 bg-emerald-50/40 dark:border-emerald-500/40 dark:bg-emerald-950/20' => $file->is_official,
    'border-zinc-200 dark:border-zinc-800' => ! $file->is_official,
])>
    <div class="flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                @if ($file->is_official)
                    <flux:badge color="emerald" icon="check-badge" size="sm">Site file</flux:badge>
                @else
                    <flux:badge color="sky" size="sm">Community</flux:badge>
                @endif
                <span class="font-semibold text-zinc-900 dark:text-white">Version {{ $file->version }}</span>
                @if ($statusColor)
                    <flux:badge :color="$statusColor" size="sm">{{ ucfirst($file->status) }}</flux:badge>
                @endif
                @if ($file->reports_count && $user?->canModerate())
                    <flux:badge color="red" size="sm" icon="flag">{{ $file->reports_count }} open {{ Str::plural('report', $file->reports_count) }}</flux:badge>
                @endif
            </div>

            <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1.5">
                    <flux:icon name="user" variant="micro" class="text-zinc-400" />
                    @if ($file->source === 'depot' && $file->depot_commit)
                        <a href="https://gitlab.com/wired420/mqmesh-depot/-/commit/{{ $file->depot_commit }}" target="_blank" rel="noopener" class="underline decoration-dotted">mqmesh-depot @ {{ substr($file->depot_commit, 0, 7) }}</a>
                    @else
                        {{ $file->sourceLabel() }}
                    @endif
                </span>
                <span class="inline-flex items-center gap-1.5" title="{{ $file->created_at }}">
                    <flux:icon name="calendar" variant="micro" class="text-zinc-400" /> {{ $file->created_at->format('M j, Y') }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <flux:icon name="scale" variant="micro" class="text-zinc-400" /> {{ $file->humanSize() }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <flux:icon name="arrow-down-tray" variant="micro" class="text-zinc-400" /> {{ number_format($file->download_count) }}
                </span>
            </div>

            @if ($file->notes)
                <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $file->notes }}</p>
            @endif

            @if ($file->status === 'rejected' && $file->rejection_reason && ($user?->id === $file->user_id || $user?->canModerate()))
                <p class="text-sm text-red-600 dark:text-red-400">Not approved: {{ $file->rejection_reason }}</p>
            @endif

            <div x-data="{ copied: null }" class="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-zinc-500">
                <button type="button" x-on:click="navigator.clipboard.writeText('{{ $file->md5 }}'); copied = 'md5'; setTimeout(() => copied = null, 1500)" class="hover:text-zinc-800 dark:hover:text-zinc-200" title="Copy MD5">
                    MD5 <span x-text="copied === 'md5' ? 'copied!' : '{{ $file->md5 }}'"></span>
                </button>
                <button type="button" x-on:click="navigator.clipboard.writeText('{{ $file->sha256 }}'); copied = 'sha'; setTimeout(() => copied = null, 1500)" class="hover:text-zinc-800 dark:hover:text-zinc-200" title="Copy SHA-256">
                    SHA-256 <span x-text="copied === 'sha' ? 'copied!' : '{{ substr($file->sha256, 0, 16) }}…'"></span>
                </button>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
            <flux:button :variant="$file->is_official ? 'primary' : 'filled'" icon="arrow-down-tray" :href="route('files.download', $file)">Download</flux:button>

            @auth
                <flux:dropdown align="end">
                    <flux:button variant="ghost" icon="ellipsis-vertical" aria-label="More actions" />
                    <flux:menu>
                        <flux:menu.item icon="flag" wire:click="openReport({{ $file->id }})">Report a problem</flux:menu.item>
                        @if (! $file->is_official && $user->isAdmin() && $file->status === 'approved')
                            <flux:menu.item icon="check-badge" wire:click="makeSiteFile({{ $file->id }})" wire:confirm="Make version {{ $file->version }} the Site file? The current Site file moves to history.">Make Site file</flux:menu.item>
                        @endif
                        @if (! $file->is_official && $user->canModerate() && $file->status === 'approved')
                            <flux:menu.item icon="eye-slash" variant="danger" wire:click="hide({{ $file->id }})" wire:confirm="Hide version {{ $file->version }} from everyone?">Hide</flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            @endauth
        </div>
    </div>
</div>
