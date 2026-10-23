<x-layouts.app title="About">
    <div class="mx-auto max-w-3xl space-y-10">
        <div>
            <flux:heading size="xl" level="1">About MQMesh</flux:heading>
            <flux:subheading>Navigation meshes for MQ2Nav, kept up to date and shared by the community.</flux:subheading>
        </div>

        <div class="space-y-4 text-zinc-700 dark:text-zinc-300">
            <p>
                I came back to EverQuest and kept having to build meshes, which is not how I planned to spend my first days back.
                The packs going around had dead spots and missing zones, and some zones took seconds to path for every pull.
                So I built and tuned my own, and this site shares the result.
            </p>
            <p>
                Thanks to ChatWithThisName and Kaen01, whose packs were the starting point, and to everyone who has committed to
                <a href="https://gitlab.com/wired420/mqmesh-depot" class="text-accent-content underline" target="_blank" rel="noopener">mqmesh-depot</a> since.
                To use this data in a project, find wired420 on the MacroQuest or RedGuides forums.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['check-badge', 'Site files', 'Each zone has one Site file, built from mqmesh-depot. It is always listed first.'],
                ['users', 'Community versions', 'Anyone with an account can upload their own version. Moderators review them before they go live.'],
                ['archive-box-arrow-down', 'Your own zips', 'Tick the zones you play, or filter by expansion, and download exactly those as one zip.'],
            ] as [$icon, $heading, $text])
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:icon :name="$icon" class="size-6 text-accent-content" />
                    <div class="mt-3 font-medium text-zinc-900 dark:text-white">{{ $heading }}</div>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
                </div>
            @endforeach
        </div>

        <div class="space-y-3">
            <flux:heading size="lg" level="2">Installing a mesh</flux:heading>
            <ol class="list-decimal space-y-2 pl-5 text-zinc-700 dark:text-zinc-300">
                <li>Download the zone's <span class="font-mono text-sm">.navmesh</span> file (and its <span class="font-mono text-sm">_doors.json</span> if it has one).</li>
                <li>Put the files in your MacroQuest <span class="font-mono text-sm">resources/MQ2Nav</span> folder. The file names must match the zone's short name, which the download already does.</li>
                <li>In game, run <span class="font-mono text-sm">/nav reload</span> or zone in again.</li>
            </ol>
        </div>
    </div>
</x-layouts.app>
