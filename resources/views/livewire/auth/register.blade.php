<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $email_confirmation = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $answer = '';

    // Hidden from people, so only a bot fills it in.
    public string $website = '';

    #[Locked]
    public int $question = 0;

    #[Locked]
    public int $startedAt = 0;

    /**
     * Pick the question and note when the form was opened.
     */
    public function mount(): void
    {
        $this->question = array_rand(config('mqmesh.signup.questions'));
        $this->startedAt = now()->getTimestamp();
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        // Every sign-up emails the address given, so cap sign-ups per IP to
        // stop anyone using the form to send mail to strangers.
        $key = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('email', 'Too many sign-ups from your network. Please try again in an hour.');

            return;
        }

        // Sign-up bots post the form about a second after loading it.
        if ($this->website !== '' || now()->getTimestamp() - $this->startedAt < config('mqmesh.signup.min_seconds')) {
            $this->addError('email', 'Something went wrong. Please wait a moment and try again.');

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'confirmed', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'answer' => ['required', 'string', function (string $attribute, string $value, \Closure $fail) {
                $answers = config("mqmesh.signup.questions.{$this->question}.answers");

                if (! in_array(strtolower(trim($value)), $answers, true)) {
                    $fail('That isn\'t the answer we were looking for.');
                }
            }],
        ], ['email.confirmed' => 'The two email addresses don\'t match.'], ['name' => 'nickname']);

        RateLimiter::hit($key, 3600);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Create an account" description="Enter your details below to create your account" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <div class="grid gap-2">
            <flux:input wire:model="name" id="name" label="{{ __('Nickname') }}" type="text" name="name" required autofocus autocomplete="nickname" placeholder="Nickname" description="Shown next to your uploads." />
        </div>

        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" id="email" label="{{ __('Email address') }}" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
        </div>

        <!-- Confirm Email Address (typed, not pasted, to catch typos) -->
        <div class="grid gap-2">
            <flux:input wire:model="email_confirmation" id="email_confirmation" label="{{ __('Confirm email address') }}" type="email" name="email_confirmation" required autocomplete="off" placeholder="Type it again" onpaste="return false" ondrop="return false" />
        </div>

        <!-- Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Password') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Password"
            />
        </div>

        <!-- Confirm Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password_confirmation"
                id="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm password"
            />
        </div>

        <!-- Question only a player can answer -->
        <div class="grid gap-2">
            <flux:input wire:model="answer" id="answer" label="{{ config('mqmesh.signup.questions.'.$question.'.question') }}" type="text" name="answer" required autocomplete="off" />
        </div>

        <!-- Honeypot -->
        <div class="absolute -left-[9999px]" aria-hidden="true">
            <label for="website">Website</label>
            <input wire:model="website" id="website" type="text" name="website" tabindex="-1" autocomplete="off" />
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Create account') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        Already have an account?
        <x-text-link href="{{ route('login') }}">Log in</x-text-link>
    </div>
</div>
