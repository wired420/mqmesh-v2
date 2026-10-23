<?php

namespace App\Console\Commands;

use App\Models\Expansion;
use App\Models\FileVersion;
use App\Services\LegacyFeeds;
use App\Services\SiteFileImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportFiles extends Command
{
    protected $signature = 'mqmesh:import-files
        {directory : Folder holding the files}
        {--type=mesh : mesh (*.navmesh) or doors (*_doors.json)}';

    protected $description = 'Import a folder of mesh or doors files as Site files (read only; the folder is never changed)';

    public function handle(SiteFileImporter $importer, LegacyFeeds $feeds): int
    {
        $type = $this->option('type');

        if (! in_array($type, ['mesh', 'doors'], true)) {
            $this->error('--type must be mesh or doors');

            return self::FAILURE;
        }

        Expansion::syncFromConfig();

        $counts = $importer->importDirectory(
            $this->argument('directory'),
            $type,
            FileVersion::SOURCE_SEED,
            report: fn (string $zone, string $what) => $this->line("  {$zone}: {$what}"),
            dateOf: fn (string $file) => Carbon::createFromTimestamp(filemtime($file)),
        );

        $feeds->write();

        $this->info(sprintf('%d added, %d updated, %d unchanged, %d failed.', ...array_values($counts)));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
