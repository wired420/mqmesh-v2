<?php

namespace App\Services;

use App\Models\FileVersion;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Stores mesh and doors files and keeps each zone's Site file pinned.
 *
 * Files are stored once per content hash and never overwritten, so a
 * version's download link keeps working after it is superseded.
 */
class FileLibrary
{
    /**
     * MQ2Nav meshes start with these four bytes.
     */
    public const MESH_MAGIC = 'TESM';

    /**
     * Check that a file on disk is a valid file of the given type.
     *
     * Returns an error message, or null when the file is fine.
     */
    public function problemWith(string $type, string $path): ?string
    {
        if (! is_file($path) || filesize($path) === 0) {
            return 'The file is empty.';
        }

        if ($type === 'mesh') {
            $handle = fopen($path, 'rb');
            $magic = fread($handle, 4);
            fclose($handle);

            return $magic === self::MESH_MAGIC
                ? null
                : 'This is not an MQ2Nav .navmesh file (it is missing the TESM header).';
        }

        if ($type === 'doors') {
            $doors = json_decode(file_get_contents($path), true);

            return is_array($doors) && array_is_list($doors)
                ? null
                : 'This is not a valid doors file (expected a JSON list of doors).';
        }

        return 'Unknown file type.';
    }

    /**
     * Store a file as a new version of a zone's mesh or doors file.
     *
     * @param  array{source: string, status?: string, user?: ?User, notes?: ?string, original_name?: ?string, depot_commit?: ?string, official?: bool, created_at?: ?\DateTimeInterface}  $attributes
     */
    public function store(Zone $zone, string $type, string $path, array $attributes): FileVersion
    {
        if (! array_key_exists($type, config('mqmesh.types'))) {
            throw new InvalidArgumentException("Unknown file type [{$type}].");
        }

        if ($problem = $this->problemWith($type, $path)) {
            throw new InvalidArgumentException($problem);
        }

        $sha256 = hash_file('sha256', $path);
        $extension = config("mqmesh.types.{$type}.extension");
        $storedPath = sprintf('%s/%s/%s.%s', substr($sha256, 0, 2), substr($sha256, 2, 2), $sha256, $extension);

        $disk = Storage::disk('meshes');

        if (! $disk->exists($storedPath)) {
            $stream = fopen($path, 'rb');
            $disk->writeStream($storedPath, $stream);
            fclose($stream);
        }

        return DB::transaction(function () use ($zone, $type, $path, $attributes, $sha256, $storedPath) {
            $version = FileVersion::where('zone_id', $zone->id)->where('type', $type)->lockForUpdate()->max('version') ?? 0;

            $file = FileVersion::create([
                'zone_id' => $zone->id,
                'type' => $type,
                'version' => $version + 1,
                'user_id' => $attributes['user']?->id ?? null,
                'source' => $attributes['source'],
                'status' => $attributes['status'] ?? FileVersion::STATUS_PENDING,
                'is_official' => false,
                'path' => $storedPath,
                'original_name' => $attributes['original_name'] ?? null,
                'size' => filesize($path),
                'md5' => md5_file($path),
                'sha256' => $sha256,
                'notes' => $attributes['notes'] ?? null,
                'depot_commit' => $attributes['depot_commit'] ?? null,
            ]);

            if ($createdAt = $attributes['created_at'] ?? null) {
                $file->forceFill(['created_at' => $createdAt])->save();
            }

            if ($attributes['official'] ?? false) {
                $this->makeSiteFile($file);
            }

            return $file;
        });
    }

    /**
     * Make a version the zone's Site file, retiring the previous one.
     */
    public function makeSiteFile(FileVersion $file, ?User $by = null): void
    {
        DB::transaction(function () use ($file, $by) {
            FileVersion::where('zone_id', $file->zone_id)
                ->where('type', $file->type)
                ->where('is_official', true)
                ->whereKeyNot($file->id)
                ->update(['is_official' => false, 'status' => FileVersion::STATUS_SUPERSEDED]);

            $file->forceFill([
                'is_official' => true,
                'status' => FileVersion::STATUS_APPROVED,
                'reviewed_by' => $by?->id ?? $file->reviewed_by,
                'reviewed_at' => $by ? now() : $file->reviewed_at,
            ])->save();

            // The zone counts as updated when its Site file changed (the depot
            // commit date on import), or now when an admin swaps it by hand.
            $zone = $file->zone;
            $zone->timestamps = false;
            $zone->forceFill(['updated_at' => $by ? now() : $file->created_at])->save();
        });

        // Imports rebuild the old updaters' feeds once at the end; a swap made
        // on the website rebuilds them straight away.
        if ($by) {
            app(LegacyFeeds::class)->write();
        }
    }

    /**
     * The absolute path of a stored version.
     */
    public function absolutePath(FileVersion $file): string
    {
        return Storage::disk('meshes')->path($file->path);
    }
}
