<?php

namespace App\Services;

use App\Models\Zone;
use Illuminate\Support\Collection;

/**
 * Writes zones.json, updater.json and meshdb.json in the exact formats the old
 * site produced, so the existing updaters keep working against this site.
 *
 * Links use /resources/meshes/<zone>.navmesh and /resources/doors/<zone>_doors.json,
 * the same paths the old site served, which this site answers with the Site file.
 */
class LegacyFeeds
{
    public const FILES = ['zones.json', 'updater.json', 'meshdb.json'];

    /**
     * Rebuild all three feeds in the public folder.
     */
    public function write(?string $directory = null): void
    {
        $directory ??= public_path();
        $zones = Zone::with(['expansion', 'siteMesh', 'siteDoors'])
            ->whereHas('siteMesh')
            ->orderBy('short_name')
            ->get();

        $this->put("{$directory}/zones.json", ['zone' => $this->zonesFeed($zones)]);
        $this->put("{$directory}/updater.json", ['zones' => $this->updaterFeed($zones)]);
        $this->put("{$directory}/meshdb.json", $this->meshDbFeed($zones));
    }

    /**
     * @return array<string, mixed>
     */
    private function zonesFeed(Collection $zones): array
    {
        return $zones->mapWithKeys(function (Zone $zone) {
            $files = ['mesh' => $this->fileEntry($zone, 'mesh', withDate: true)];

            if ($zone->siteDoors) {
                $files['doors'] = $this->fileEntry($zone, 'doors', withDate: true);
            }

            return [$zone->short_name => ['files' => $files, ...$this->zoneInfo($zone)]];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function updaterFeed(Collection $zones): array
    {
        return $zones->mapWithKeys(fn (Zone $zone) => [$zone->short_name => [
            'files' => ['mesh' => $this->fileEntry($zone, 'mesh', withDate: false)],
            ...$this->zoneInfo($zone),
        ]])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function meshDbFeed(Collection $zones): array
    {
        return $zones->mapWithKeys(fn (Zone $zone) => [$zone->short_name => [
            'link' => $this->link($zone, 'mesh'),
            'hash' => strtoupper($zone->siteMesh->md5),
        ]])->all();
    }

    /**
     * @return array<string, string>
     */
    private function fileEntry(Zone $zone, string $type, bool $withDate): array
    {
        $file = $zone->siteFile($type);

        $entry = ['link' => $this->link($zone, $type)];

        if ($withDate) {
            $entry['lastmodified'] = $file->created_at->timezone(config('app.timezone'))->format('F d Y H:i');
        }

        return $entry + ['hash' => $file->md5, 'size' => $this->size($file->size)];
    }

    /**
     * @return array<string, string>
     */
    private function zoneInfo(Zone $zone): array
    {
        return [
            'shortname' => $zone->short_name,
            'id' => (string) ($zone->eq_zone_id ?? ''),
            'name' => $zone->name,
            'expansion' => $zone->expansion->code,
        ];
    }

    private function link(Zone $zone, string $type): string
    {
        return $type === 'doors'
            ? url("resources/doors/{$zone->short_name}_doors.json")
            : url("resources/meshes/{$zone->short_name}.navmesh");
    }

    /**
     * The old site's size format, such as 5.53MB or 48.5KB.
     */
    private function size(int $bytes): string
    {
        foreach (['TB' => 1024 ** 4, 'GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024, 'B' => 1] as $unit => $value) {
            if ($bytes >= $value) {
                return (string) round($bytes / $value, 2).$unit;
            }
        }

        return '0B';
    }

    /**
     * Write a file atomically so updaters never read half a feed.
     */
    private function put(string $path, array $data): void
    {
        $temp = $path.'.'.getmypid().'.tmp';
        file_put_contents($temp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        rename($temp, $path);
    }
}
