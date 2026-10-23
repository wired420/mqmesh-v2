<?php

use App\Support\UpdaterReleases;
use Flux\Flux;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * The admin-only form on the Updater page. It is its own component so that
 * nobody else can even start a file upload to it.
 */
new class extends Component {
    use WithFileUploads;

    public $windows = null;

    public $linux = null;

    /**
     * Runs on every request, including each file upload.
     */
    public function boot(): void
    {
        Gate::authorize('admin');
    }

    /**
     * Publish a new version. Both builds are required.
     */
    public function publish(): void
    {
        $this->validate([
            'windows' => ['required', 'file', 'max:'.config('mqmesh.max_upload_kb')],
            'linux' => ['required', 'file', 'max:'.config('mqmesh.max_upload_kb')],
        ], [
            'windows.required' => 'Add the Windows build.',
            'linux.required' => 'Add the Linux build.',
        ]);

        $versions = [];

        foreach (['windows', 'linux'] as $platform) {
            $check = UpdaterReleases::inspect($platform, $this->{$platform}->getClientOriginalName(), $this->{$platform}->getRealPath());

            if ($check['error']) {
                $this->addError($platform, $check['error']);
            }

            $versions[$platform] = $check['version'];
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($versions['windows'] !== $versions['linux']) {
            $this->addError('linux', "Both builds must be the same version (Windows is {$versions['windows']}, Linux is {$versions['linux']}).");

            return;
        }

        $version = $versions['windows'];
        $current = UpdaterReleases::latest()->pluck('version')->first();

        if ($current && version_compare($version, $current, '<=')) {
            $this->addError('windows', "Version {$version} is not newer than the current {$current}.");

            return;
        }

        File::ensureDirectoryExists(UpdaterReleases::directory());

        foreach (['windows', 'linux'] as $platform) {
            File::copy($this->{$platform}->getRealPath(), UpdaterReleases::directory().'/'.$this->{$platform}->getClientOriginalName());
            $this->{$platform}->delete();
        }

        $this->reset('windows', 'linux');
        $this->dispatch('updater-published');

        Flux::toast(variant: 'success', text: "Version {$version} is now on the Updater page.");
    }
}; ?>

<form wire:submit="publish" class="space-y-5 rounded-xl border border-amber-500/40 bg-amber-50/40 p-5 dark:bg-amber-950/10">
    <div>
        <flux:heading size="lg" level="2">Publish a new build</flux:heading>
        <flux:subheading>Admins only. Add both builds of the same, newer version; they replace the ones above together.</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach (['windows' => 'Windows build (MQMeshUpdater-X.Y.Z-win-x64.zip)', 'linux' => 'Linux build (mqmesh-updater-X.Y.Z-linux-x64.tar.gz)'] as $field => $label)
            <flux:field>
                <flux:label>{{ $label }}</flux:label>
                <input type="file" wire:model="{{ $field }}" accept="{{ $field === 'windows' ? '.zip' : '.gz,.tar.gz' }}"
                    class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-200 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-100" />
                <div wire:loading wire:target="{{ $field }}" class="text-xs text-zinc-500">Uploading…</div>
                <flux:error :name="$field" />
            </flux:field>
        @endforeach
    </div>

    <div class="flex justify-end">
        <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="windows,linux,publish">Publish</flux:button>
    </div>
</form>
