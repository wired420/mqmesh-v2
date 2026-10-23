<?php

namespace App\Console\Commands;

use App\Models\Expansion;
use App\Models\FileVersion;
use App\Services\SiteFileImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SyncDepot extends Command
{
    protected $signature = 'mqmesh:sync-depot {--no-pull : Import from the local clone without pulling first}';

    protected $description = 'Pull mqmesh-depot (read only) and make any changed meshes the new Site files';

    public function handle(SiteFileImporter $importer): int
    {
        $path = config('mqmesh.depot.path');

        if (! $this->option('no-pull')) {
            $git = is_dir("{$path}/.git")
                ? Process::path($path)->timeout(600)->run(['git', 'pull', '--ff-only', '--quiet'])
                : Process::timeout(1800)->run(['git', 'clone', '--quiet', config('mqmesh.depot.url'), $path]);

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
        );

        $this->info(sprintf('Depot at %s: %d added, %d updated, %d unchanged, %d failed.', substr((string) $commit, 0, 7), ...array_values($counts)));

        return self::SUCCESS;
    }
}
