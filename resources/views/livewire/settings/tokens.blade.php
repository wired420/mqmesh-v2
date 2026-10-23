<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';

    public bool $canUpload = true;

    public ?string $plainToken = null;

    /**
     * Tokens can upload, so they need a confirmed email too.
     */
    public function boot(): void
    {
        abort_unless(Auth::user()?->hasVerifiedEmail(), 403);
    }

    #[Computed]
    public function tokens()
    {
        return Auth::user()->tokens()->latest()->get();
    }

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:60']]);

        $abilities = $this->canUpload ? ['read', 'upload'] : ['read'];
        $this->plainToken = Auth::user()->createToken($this->name, $abilities)->plainTextToken;

        $this->reset('name');
        unset($this->tokens);
    }

    public function revoke(int $id): void
    {
        Auth::user()->tokens()->whereKey($id)->delete();
        unset($this->tokens);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="API tokens" subheading="Let a tool such as a mesh updater use your account. Reading the API needs no token.">
        <form wire:submit="create" class="my-6 space-y-4">
            <flux:input wire:model="name" label="Token name" placeholder="My updater" />
            <flux:checkbox wire:model="canUpload" label="Allow uploads" description="Uploads made with this token follow the same review rules as the website." />
            <flux:button type="submit" variant="primary">Create token</flux:button>
        </form>

        @if ($plainToken)
            <flux:callout icon="key" color="emerald" class="mb-6">
                <flux:callout.heading>Copy your new token now</flux:callout.heading>
                <flux:callout.text>It won't be shown again.</flux:callout.text>
                <div x-data class="mt-2 flex items-center gap-2">
                    <code class="min-w-0 flex-1 truncate rounded bg-white px-2 py-1 font-mono text-xs dark:bg-zinc-900">{{ $plainToken }}</code>
                    <flux:button size="xs" x-on:click="navigator.clipboard.writeText('{{ $plainToken }}'); $el.innerText = 'Copied'">Copy</flux:button>
                </div>
            </flux:callout>
        @endif

        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @forelse ($this->tokens as $token)
                <div wire:key="token-{{ $token->id }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                        <div class="font-medium">{{ $token->name }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ implode(', ', $token->abilities) }} &middot;
                            {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }}
                        </div>
                    </div>
                    <flux:button size="xs" variant="danger" wire:click="revoke({{ $token->id }})" wire:confirm="Revoke this token? Anything using it stops working.">Revoke</flux:button>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-sm text-zinc-500">No tokens yet.</div>
            @endforelse
        </div>
    </x-settings.layout>
</section>
