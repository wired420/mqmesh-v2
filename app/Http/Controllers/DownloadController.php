<?php

namespace App\Http\Controllers;

use App\Models\FileVersion;
use App\Models\Zone;
use App\Services\FileLibrary;
use App\Support\UpdaterReleases;
use App\Support\ZoneFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

class DownloadController extends Controller
{
    public function __construct(private FileLibrary $library)
    {
    }

    /**
     * Download one file under the name MQ2Nav expects.
     */
    public function file(Request $request, FileVersion $file): BinaryFileResponse
    {
        abort_unless($file->isVisibleTo($request->user()), 404);

        // Counting a download is not an edit, so leave updated_at alone.
        FileVersion::whereKey($file->id)->toBase()->increment('download_count');

        return response()->download($this->library->absolutePath($file), $file->downloadName(), [
            'Content-Type' => $file->type === 'doors' ? 'application/json' : 'application/octet-stream',
            'ETag' => '"'.$file->md5.'"',
        ]);
    }

    /**
     * Download a build of the MQMesh Updater listed on the Updater page.
     */
    public function updater(string $file): BinaryFileResponse
    {
        abort_unless(UpdaterReleases::isOffered($file), 404);

        return response()->download(UpdaterReleases::directory().'/'.$file, $file);
    }

    /**
     * Serve a zone's Site file at the old site's path, such as
     * /resources/meshes/poknowledge.navmesh, so old links and updaters work.
     */
    public function legacy(Request $request, string $name): BinaryFileResponse
    {
        $zone = Zone::where('short_name', strtolower($name))->firstOrFail();
        $file = $zone->siteFile($request->route('type'));

        abort_unless($file, 404);

        return $this->file($request, $file);
    }

    /**
     * Stream a zip of Site files, built while it downloads.
     *
     * Pick zones with ?zones=a,b,c, or pass the zone browser's filters to get
     * every matching zone. Add ?with=doors to include doors files.
     */
    public function zip(Request $request): StreamedResponse
    {
        $zones = $request->filled('zones')
            ? Zone::whereIn('short_name', explode(',', (string) $request->query('zones')))->orderBy('short_name')
            : ZoneFilters::fromRequest($request)->query();

        $types = $request->query('with') === 'doors' ? ['mesh', 'doors'] : ['mesh'];

        $files = FileVersion::query()
            ->with('zone')
            ->whereIn('zone_id', $zones->select('zones.id'))
            ->whereIn('type', $types)
            ->where('is_official', true)
            ->get();

        abort_if($files->isEmpty(), 404, 'None of those zones have files yet.');

        FileVersion::whereKey($files->modelKeys())->toBase()->increment('download_count');

        $name = $this->zipName($request, $files->count());

        return response()->stream(function () use ($files) {
            $zip = new ZipStream(
                sendHttpHeaders: false,
                defaultCompressionMethod: CompressionMethod::STORE,
                defaultEnableZeroHeader: true,
                enableZip64: true,
            );

            foreach ($files as $file) {
                $zip->addFileFromPath(
                    fileName: $file->downloadName(),
                    path: $this->library->absolutePath($file),
                    lastModificationDateTime: $file->created_at,
                );
            }

            $zip->finish();
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * A readable file name for the zip.
     */
    private function zipName(Request $request, int $count): string
    {
        $expansions = (array) $request->query('exp', []);

        $label = match (true) {
            $request->filled('zones') && $count <= 2 => str_replace(',', '-', (string) $request->query('zones')),
            count($expansions) === 1 && ! $request->filled('zones') => $expansions[0],
            default => "{$count}-files",
        };

        return 'mqmesh-'.preg_replace('/[^a-z0-9_-]/i', '', $label).'-'.now()->format('Y-m-d').'.zip';
    }
}
