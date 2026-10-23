<?php

namespace App\Services;

use App\Models\Expansion;
use App\Models\FileVersion;
use App\Models\Zone;
use Closure;

/**
 * Imports a folder of mesh or doors files as each zone's Site file.
 */
class SiteFileImporter
{
    public function __construct(private FileLibrary $library)
    {
    }

    /**
     * Import every file of a type from a folder.
     *
     * A file whose content already matches the zone's Site file is skipped,
     * so running this again only picks up changes.
     *
     * @param  Closure(string, string): void|null  $report  Called with the zone short name and what happened.
     * @return array{added: int, updated: int, unchanged: int, failed: int}
     */
    public function importDirectory(string $directory, string $type, string $source, ?string $commit = null, ?Closure $report = null): array
    {
        $counts = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];
        $suffix = $type === 'doors' ? '_doors.json' : '.navmesh';
        $files = glob(rtrim($directory, '/').'/*'.$suffix) ?: [];
        sort($files);

        foreach ($files as $path) {
            $shortName = strtolower(basename($path, $suffix));
            $zone = $this->zoneFor($shortName);
            $current = $zone->siteFile($type);

            if ($current && $current->sha256 === hash_file('sha256', $path)) {
                $counts['unchanged']++;

                continue;
            }

            try {
                $this->library->store($zone, $type, $path, [
                    'source' => $source,
                    'status' => FileVersion::STATUS_APPROVED,
                    'original_name' => basename($path),
                    'depot_commit' => $commit,
                    'official' => true,
                ]);
            } catch (\InvalidArgumentException $e) {
                $counts['failed']++;
                $report && $report($shortName, 'skipped: '.$e->getMessage());

                continue;
            }

            $zone->unsetRelation($type === 'doors' ? 'siteDoors' : 'siteMesh');
            $counts[$current ? 'updated' : 'added']++;
            $report && $report($shortName, $current ? 'updated' : 'added');
        }

        return $counts;
    }

    /**
     * Find a zone by short name, adding it under Other when it is new.
     */
    private function zoneFor(string $shortName): Zone
    {
        return Zone::firstOrCreate(
            ['short_name' => $shortName],
            ['name' => $shortName, 'expansion_id' => Expansion::where('code', 'other')->value('id')],
        );
    }
}
