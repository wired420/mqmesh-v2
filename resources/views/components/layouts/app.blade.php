<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-200">
        @php
            $user = auth()->user();
            $queue = $user?->canModerate()
                ? \App\Models\FileVersion::where('status', 'pending')->count() + \App\Models\Report::open()->count()
                : 0;
        @endphp

        <flux:header container class="sticky top-0 z-30 border-b border-zinc-200 bg-white/85 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/85">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('home') }}" class="ml-2 mr-6 flex items-center space-x-2 lg:ml-0" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="map" :href="route('zones.index')" :current="request()->routeIs('zones.*')" wire:navigate>Zones</flux:navbar.item>
                <flux:navbar.item icon="arrow-up-tray" :href="route('uploads.create')" :current="request()->routeIs('uploads.create')" wire:navigate>Upload</flux:navbar.item>
                <flux:navbar.item icon="code-bracket" :href="route('api.docs')" :current="request()->routeIs('api.docs')" wire:navigate>API</flux:navbar.item>
                <flux:navbar.item icon="information-circle" :href="route('about')" :current="request()->routeIs('about')" wire:navigate>About</flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="mr-1.5 space-x-0.5 py-0!">
                <flux:tooltip content="Search zones" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" :href="route('zones.index')" label="Search zones" wire:navigate />
                </flux:tooltip>
                @if ($user?->canModerate())
                    <flux:tooltip content="Moderation queue" position="bottom">
                        <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="shield-check" :href="route('moderation')" :badge="$queue ?: null" badge-color="amber" label="Moderation" wire:navigate />
                    </flux:tooltip>
                @endif
            </flux:navbar>

            @auth
                <flux:dropdown position="top" align="end">
                    <flux:profile class="cursor-pointer" :initials="$user->initials()" />

                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {{ $user->initials() }}
                                        </span>
                                    </span>

                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ $user->name }}</span>
                                        <span class="truncate text-xs">{{ $user->email }}</span>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item :href="route('dashboard')" icon="folder" wire:navigate>My uploads</flux:menu.item>
                            <flux:menu.item :href="route('settings.tokens')" icon="key" wire:navigate>API tokens</flux:menu.item>
                            <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Settings</flux:menu.item>
                            @if ($user->canModerate())
                                <flux:menu.item :href="route('moderation')" icon="shield-check" wire:navigate>Moderation</flux:menu.item>
                            @endif
                            @if ($user->isAdmin())
                                <flux:menu.item :href="route('admin.users')" icon="users" wire:navigate>Users</flux:menu.item>
                            @endif
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                {{ __('Log Out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" size="sm" :href="route('login')" wire:navigate>Log in</flux:button>
                    <flux:button variant="primary" size="sm" :href="route('register')" wire:navigate class="max-sm:hidden">Sign up</flux:button>
                </div>
            @endauth
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar stashable sticky class="lg:hidden border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('home') }}" class="ml-1 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>Home</flux:navlist.item>
                <flux:navlist.item icon="map" :href="route('zones.index')" :current="request()->routeIs('zones.*')" wire:navigate>Zones</flux:navlist.item>
                <flux:navlist.item icon="arrow-up-tray" :href="route('uploads.create')" wire:navigate>Upload</flux:navlist.item>
                <flux:navlist.item icon="code-bracket" :href="route('api.docs')" wire:navigate>API</flux:navlist.item>
                <flux:navlist.item icon="information-circle" :href="route('about')" wire:navigate>About</flux:navlist.item>
            </flux:navlist>
        </flux:sidebar>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p class="max-w-2xl">
                    <a href="https://www.everquest.com/" class="underline" target="_blank" rel="noopener">EverQuest&trade;</a> and its owner
                    <a href="https://www.daybreakgames.com/" class="underline" target="_blank" rel="noopener">Daybreak Game Company, LLC</a>
                    are in no way affiliated with this site. Using this site's content may violate the EULA or Terms of Service of those entities.
                </p>
                <div class="flex shrink-0 gap-4">
                    <a href="{{ route('about') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200" wire:navigate>About</a>
                    <a href="{{ route('api.docs') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200" wire:navigate>API</a>
                    <a href="https://gitlab.com/wired420/mqmesh-depot" class="hover:text-zinc-800 dark:hover:text-zinc-200" target="_blank" rel="noopener">mqmesh-depot</a>
                </div>
            </div>
        </footer>

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
