<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expansion;
use App\Models\FileVersion;
use App\Models\Zone;
use App\Support\ZoneFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CatalogueController extends Controller
{
    /**
     * Every zone's Site files in one response, for updaters.
     *
     * Send the ETag back as If-None-Match to get a 304 when nothing changed,
     * or pass ?since=2026-01-01T00:00:00Z for only the zones changed since then.
     * Add ?include=community to list each zone's approved community versions too.
     */
    public function manifest(Request $request): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date'], 'include' => ['nullable', 'in:community']]);

        $withCommunity = $request->query('include') === 'community';

        $latest = FileVersion::where('is_official', true)->max('updated_at');
        $tag = $latest.'|'.FileVersion::where('is_official', true)->count().'|'.$request->query('since');

        if ($withCommunity) {
            $community = $this->communityQuery(FileVersion::query());
            $tag .= '|community|'.(clone $community)->max('updated_at').'|'.$community->count();
        }

        $etag = '"'.md5($tag).'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->setEtag(trim($etag, '"'));
        }

        $zones = Zone::query()
            ->with(['expansion', 'siteMesh', 'siteDoors'])
            ->when($withCommunity, fn ($query) => $query->with(['communityVersions' => fn ($query) => $query->with('user')->latest()->latest('id')]))
            ->whereHas('siteMesh')
            ->when($request->query('since'), fn ($query, $since) => $query->where('updated_at', '>=', Carbon::parse($since)))
            ->orderBy('short_name')
            ->get();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'count' => $zones->count(),
            'zones' => $zones->map(fn (Zone $zone) => $withCommunity
                ? [...$this->zoneSummary($zone), 'community' => $this->communitySummaries($zone->communityVersions)]
                : $this->zoneSummary($zone)),
        ])->setEtag(trim($etag, '"'));
    }

    /**
     * Approved community versions: the same rule as Zone::communityVersions.
     */
    private function communityQuery($query)
    {
        return $query->where('status', FileVersion::STATUS_APPROVED)->where('is_official', false);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function communitySummaries($files)
    {
        return $files->map(fn (FileVersion $file) => [
            ...$this->fileSummary($file),
            'uploaded_by' => $file->user?->name,
            'notes' => $file->notes,
        ])->values();
    }

    /**
     * The expansions in release order.
     */
    public function expansions(): JsonResponse
    {
        return response()->json([
            'data' => Expansion::withCount('zones')->orderBy('sort_order')->get()->map(fn (Expansion $expansion) => [
                'code' => $expansion->code,
                'name' => $expansion->name,
                'year' => $expansion->year,
                'zones' => $expansion->zones_count,
            ]),
        ]);
    }

    /**
     * Zones matching the same filters as the website's zone browser.
     */
    public function zones(Request $request): JsonResponse
    {
        $zones = ZoneFilters::fromRequest($request)->query()
            ->with(['expansion', 'siteMesh', 'siteDoors'])
            ->paginate(min(max((int) $request->query('per_page', 100), 1), 500))
            ->withQueryString();

        return response()->json([
            'data' => $zones->getCollection()->map(fn (Zone $zone) => $this->zoneSummary($zone)),
            'meta' => ['total' => $zones->total(), 'page' => $zones->currentPage(), 'last_page' => $zones->lastPage()],
            'links' => ['next' => $zones->nextPageUrl(), 'prev' => $zones->previousPageUrl()],
        ]);
    }

    /**
     * One zone with its Site files and approved community versions.
     */
    public function zone(Zone $zone): JsonResponse
    {
        $zone->load(['expansion', 'siteMesh', 'siteDoors']);

        $community = $zone->communityVersions()->with('user')->latest()->latest('id')->get();

        return response()->json([
            ...$this->zoneSummary($zone),
            'community' => $this->communitySummaries($community),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function zoneSummary(Zone $zone): array
    {
        return [
            'short_name' => $zone->short_name,
            'name' => $zone->name,
            'eq_zone_id' => $zone->eq_zone_id,
            'expansion' => $zone->expansion->code,
            'updated_at' => $zone->updated_at?->toIso8601String(),
            'url' => route('zones.show', $zone),
            'files' => array_filter([
                'mesh' => $zone->siteMesh ? $this->fileSummary($zone->siteMesh) : null,
                'doors' => $zone->siteDoors ? $this->fileSummary($zone->siteDoors) : null,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileSummary(FileVersion $file): array
    {
        return [
            'id' => $file->id,
            'type' => $file->type,
            'version' => $file->version,
            'site_file' => $file->is_official,
            'file_name' => sprintf(config("mqmesh.types.{$file->type}.download_name"), $file->zone?->short_name),
            'size' => $file->size,
            'md5' => $file->md5,
            'sha256' => $file->sha256,
            'created_at' => $file->created_at->toIso8601String(),
            'download_url' => route('api.files.download', $file),
        ];
    }
}
