<?php

namespace App\Support;

use App\Models\Expansion;
use App\Models\FileVersion;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The zone browser's filters, shared by the browser, zip downloads and the API
 * so a filtered view always means the same set of zones.
 */
class ZoneFilters
{
    public const SORTS = ['name', 'expansion', 'updated', 'size', 'downloads'];

    public const UPDATED = ['30d' => 'Last 30 days', '90d' => 'Last 90 days', 'year' => 'Last 12 months'];

    /**
     * @param  list<string>  $expansions  Expansion codes.
     */
    public function __construct(
        public string $search = '',
        public array $expansions = [],
        public bool $hasDoors = false,
        public bool $hasCommunity = false,
        public string $updated = '',
        public string $sort = 'name',
        public string $direction = 'asc',
    ) {
    }

    /**
     * Read the filters from a request's query string.
     */
    public static function fromRequest(Request $request): self
    {
        $expansions = $request->query('exp', []);

        return new self(
            search: (string) $request->query('q', ''),
            expansions: array_values(array_filter(is_array($expansions) ? $expansions : explode(',', (string) $expansions))),
            hasDoors: $request->boolean('doors'),
            hasCommunity: $request->boolean('community'),
            updated: (string) $request->query('updated', ''),
            sort: (string) $request->query('sort', 'name'),
            direction: (string) $request->query('dir', 'asc'),
        );
    }

    /**
     * The filters as a query string array, leaving out defaults.
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->search ?: null,
            'exp' => $this->expansions ?: null,
            'doors' => $this->hasDoors ? 1 : null,
            'community' => $this->hasCommunity ? 1 : null,
            'updated' => $this->updated ?: null,
        ]);
    }

    /**
     * Build the filtered, sorted zone query.
     */
    public function query(): Builder
    {
        $query = Zone::query()->search($this->search);

        if ($this->expansions) {
            $query->whereIn('expansion_id', Expansion::whereIn('code', $this->expansions)->select('id'));
        }

        if ($this->hasDoors) {
            $query->whereHas('siteDoors');
        }

        if ($this->hasCommunity) {
            $query->whereHas('communityVersions');
        }

        if ($since = match ($this->updated) {
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'year' => now()->subYear(),
            default => null,
        }) {
            $query->where('updated_at', '>=', $since);
        }

        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        match (in_array($this->sort, self::SORTS, true) ? $this->sort : 'name') {
            'expansion' => $query->orderBy(
                Expansion::select('sort_order')->whereColumn('expansions.id', 'zones.expansion_id'),
                $direction,
            ),
            'updated' => $query->orderBy('updated_at', $direction),
            'size' => $query->orderBy(
                FileVersion::select('size')->whereColumn('file_versions.zone_id', 'zones.id')
                    ->where('type', 'mesh')->where('is_official', true)->limit(1),
                $direction,
            ),
            'downloads' => $query->orderBy(
                FileVersion::selectRaw('coalesce(sum(download_count), 0)')->whereColumn('file_versions.zone_id', 'zones.id'),
                $direction,
            ),
            default => null,
        };

        return $query->orderBy('name', $this->sort === 'name' ? $direction : 'asc');
    }
}
