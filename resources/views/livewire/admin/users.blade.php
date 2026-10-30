<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url]
    public string $q = '';

    /**
     * Runs on every request, including each button press.
     */
    public function boot(): void
    {
        Gate::authorize('admin');
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->when(trim($this->q) !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->q}%")->orWhere('email', 'like', "%{$this->q}%")))
            ->withCount('uploads')
            ->orderByRaw("case role when 'admin' then 0 when 'moderator' then 1 else 2 end")
            ->orderBy('name')
            ->paginate(30);
    }

    public function setRole(int $id, string $role): void
    {
        abort_unless(in_array($role, [User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN], true), 422);

        $user = User::findOrFail($id);

        if ($user->is(Auth::user()) && $role !== User::ROLE_ADMIN) {
            Flux::toast(variant: 'danger', text: "You can't remove your own admin role.");

            return;
        }

        $user->forceFill(['role' => $role])->save();
        unset($this->users);
        Flux::toast(text: "{$user->name} is now {$role}.");
    }

    public function toggleTrusted(int $id): void
    {
        $user = User::findOrFail($id);
        $user->forceFill(['is_trusted' => ! $user->is_trusted])->save();

        unset($this->users);
        Flux::toast(text: $user->is_trusted ? "{$user->name}'s uploads now skip review." : "{$user->name}'s uploads need review again.");
    }

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->is(Auth::user())) {
            Flux::toast(variant: 'danger', text: "You can't delete your own account here.");

            return;
        }

        if ($user->isAdmin()) {
            Flux::toast(variant: 'danger', text: "Remove {$user->name}'s admin role first.");

            return;
        }

        $user->delete();

        unset($this->users);
        Flux::toast(text: "{$user->name} was deleted.");
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Users</flux:heading>
            <flux:subheading>Moderators review uploads and reports. Trusted users' uploads go live without review.</flux:subheading>
        </div>
        <flux:input wire:model.live.debounce.250ms="q" icon="magnifying-glass" placeholder="Search name or email" class="sm:w-72" />
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60">
                <tr>
                    <th class="px-4 py-3 font-medium">User</th>
                    <th class="px-4 py-3 font-medium">Joined</th>
                    <th class="px-4 py-3 font-medium">Uploads</th>
                    <th class="px-4 py-3 font-medium">Trusted</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium"><span class="sr-only">Delete</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $user->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                            @unless ($user->hasVerifiedEmail())
                                <flux:badge size="sm" color="amber" class="mt-1">Unverified</flux:badge>
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ $user->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $user->uploads_count }}</td>
                        <td class="px-4 py-3">
                            <flux:switch :checked="$user->is_trusted" wire:click="toggleTrusted({{ $user->id }})" />
                        </td>
                        <td class="px-4 py-3">
                            <flux:select size="sm" class="w-36" wire:change="setRole({{ $user->id }}, $event.target.value)">
                                @foreach (['user' => 'User', 'moderator' => 'Moderator', 'admin' => 'Admin'] as $value => $label)
                                    <flux:select.option :value="$value" :selected="$user->role === $value">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @unless ($user->isAdmin())
                                <flux:button size="sm" variant="danger" icon="trash"
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Delete {{ $user->name }} ({{ $user->email }})? This can't be undone. Any uploads stay on the site.">
                                    Delete
                                </flux:button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $this->users->links() }}
</div>
