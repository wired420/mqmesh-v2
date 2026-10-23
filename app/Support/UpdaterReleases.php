<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The MQMesh Updater builds offered on the Updater page.
 *
 * Releasing a new version needs no deploy: copy the files into
 * storage/app/releases/updater and the page picks up the newest one per
 * platform from the version in the file name.
 */
class UpdaterReleases
{
    /**
     * File name patterns per platform. The version is the first capture.
     */
    public const PLATFORMS = [
        'windows' => [
            'pattern' => '/^MQMeshUpdater-(\d+\.\d+\.\d+)-win-x64\.zip$/',
            'label' => 'Windows',
            'arch' => 'x64',
            'icon' => 'computer-desktop',
            'needs' => 'Windows 10 or 11 with the .NET 10 Desktop Runtime (Windows offers it if it\'s missing).',
            'steps' => [
                'Unzip and put MQMeshUpdater.exe in your MacroQuest folder.',
                'Run it, then click Update.',
            ],
        ],
        'linux' => [
            'pattern' => '/^mqmesh-updater-(\d+\.\d+\.\d+)-linux-x64\.tar\.gz$/',
            'label' => 'Linux',
            'arch' => 'x64',
            'icon' => 'command-line',
            'needs' => 'For MacroQuest under Wine or Proton on 64-bit Linux (X11 or XWayland). One self-contained file, no .NET needed.',
            'steps' => [
                'Extract it and copy mqmesh-updater into your MacroQuest folder inside the Wine prefix.',
                'Make it executable (chmod +x mqmesh-updater), run it, then click Update.',
            ],
        ],
    ];

    public static function directory(): string
    {
        return storage_path('app/releases/updater');
    }

    /**
     * The newest build for each platform that has one.
     *
     * @return Collection<string, array{platform: string, version: string, file: string, size: int, sha256: string, date: \Illuminate\Support\Carbon}>
     */
    public static function latest(): Collection
    {
        $files = collect(glob(self::directory().'/*') ?: [])->map(fn ($path) => basename($path));

        return collect(self::PLATFORMS)->map(function (array $platform, string $key) use ($files) {
            $best = $files
                ->map(fn ($file) => preg_match($platform['pattern'], $file, $m) ? ['file' => $file, 'version' => $m[1]] : null)
                ->filter()
                ->sort(fn ($a, $b) => version_compare($b['version'], $a['version']))
                ->first();

            return $best ? [...$platform, ...$best, ...self::details($best['file']), 'platform' => $key] : null;
        })->filter();
    }

    /**
     * Whether a file name is one of the offered builds.
     */
    public static function isOffered(string $file): bool
    {
        return self::latest()->pluck('file')->contains($file);
    }

    /**
     * Size, date and SHA-256, cached until the file changes.
     *
     * @return array{size: int, sha256: string, date: \Illuminate\Support\Carbon}
     */
    private static function details(string $file): array
    {
        $path = self::directory().'/'.$file;
        $mtime = filemtime($path);

        $sha256 = Cache::rememberForever("updater-sha256:{$file}:{$mtime}", fn () => hash_file('sha256', $path));

        return ['size' => filesize($path), 'sha256' => $sha256, 'date' => \Illuminate\Support\Carbon::createFromTimestamp($mtime)];
    }
}
