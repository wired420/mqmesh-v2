<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public ?string $status = null;

    public bool $error = false;

    /**
     * Send an email verification notification to the user, at most every 15 minutes.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        $wait = Auth::user()->sendVerificationEmailThrottled();

        $this->error = $wait > 0;
        $this->status = $wait > 0
            ? __('You can ask for another link in :minutes.', ['minutes' => trans_choice('{1} 1 minute|[2,*] :count minutes', (int) ceil($wait / 60))])
            : __('A new verification link is on its way to :email.', ['email' => Auth::user()->email]);
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="mt-4 flex flex-col gap-6">
    <x-auth-header title="Confirm your email" description="We sent a link to {{ auth()->user()->email }}. Click it to finish setting up your account." />

    <div class="space-y-2 text-center text-sm text-zinc-600 dark:text-zinc-400">
        <p>You can browse and download without confirming, but uploading needs a confirmed address.</p>
        <p>The link works for one hour. You can ask for a new one every 15 minutes.</p>
    </div>

    @if ($status)
        <div @class(['text-center text-sm font-medium', 'text-amber-600' => $error, 'text-green-600' => ! $error])>
            {{ $status }}
        </div>
    @endif

    <div class="flex flex-col items-center justify-between space-y-3">
        <flux:button wire:click="sendVerification" variant="primary" class="w-full">
            {{ __('Send a new link') }}
        </flux:button>

        <div class="flex gap-4 text-sm">
            <a href="{{ route('home') }}" class="text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400" wire:navigate>Back to the site</a>
            <button wire:click="logout" type="submit" class="text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400">
                {{ __('Log out') }}
            </button>
        </div>
    </div>
</div>
