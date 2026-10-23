<?php

namespace App\Console\Commands;

use App\Models\Expansion;
use App\Models\FileVersion;
use App\Services\LegacyFeeds;
use App\Services\SiteFileImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class SyncDepot extends Command
{
    protected $signature = 'mqmesh:sync-depot {--no-pull : Import from the local clone without pulling first}';

    protected $description = 'Pull mqmesh-depot (read only) and make any changed meshes the new Site files';

    public function handle(SiteFileImporter $importer, LegacyFeeds $feeds): int
    {
        // One sync at a time, whether the webhook, the schedule or a person started it.
        $lock = Cache::lock('mqmesh-depot-sync', 3600);

        if (! $lock->get()) {
            $this->warn('A depot sync is already running.');

            return self::SUCCESS;
        }

        try {
            return $this->sync($importer, $feeds);
        } finally {
            $lock->release();
        }
    }

    private function sync(SiteFileImporter $importer, LegacyFeeds $feeds): int
    {
        $path = config('mqmesh.depot.path');

        if (! $this->option('no-pull')) {
            $git = is_dir("{$path}/.git")
                ? Process::path($path)->timeout(600)->run(['git', 'pull', '--ff-only', '--quiet'])
                // A blobless clone keeps the commit history (for file dates)
                // without downloading every old version of every mesh.
                : Process::timeout(1800)->run(['git', 'clone', '--quiet', '--filter=blob:none', config('mqmesh.depot.url'), $path]);

            if ($git->failed()) {
                $this->error('git failed: '.trim($git->errorOutput()));

                return self::FAILURE;
            }
        }

        $commit = trim(Process::path($path)->run(['git', 'rev-parse', 'HEAD'])->output()) ?: null;

        Expansion::syncFromConfig();

        $counts = $importer->importDirectory(
            $path,
            'mesh',
            FileVersion::SOURCE_DEPOT,
            $commit,
            fn (string $zone, string $what) => $this->line("  {$zone}: {$what}"),
            function (string $file) use ($path) {
                $date = trim(Process::path($path)->run(['git', 'log', '-1', '--format=%cI', '--', basename($file)])->output());

                return $date !== '' ? Carbon::parse($date) : null;
            },
        );

        if ($counts['added'] || $counts['updated'] || ! file_exists(public_path('zones.json'))) {
            $feeds->write();
        }

        $this->info(sprintf('Depot at %s: %d added, %d updated, %d unchanged, %d failed.', substr((string) $commit, 0, 7), ...array_values($counts)));

        return self::SUCCESS;
    }
}
