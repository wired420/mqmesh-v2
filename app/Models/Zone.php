<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Zone extends Model
{
    /** @use HasFactory<\Database\Factories\ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'short_name',
        'eq_zone_id',
        'name',
        'expansion_id',
    ];

    /**
     * Get the expansion the zone belongs to.
     */
    public function expansion(): BelongsTo
    {
        return $this->belongsTo(Expansion::class);
    }

    /**
     * Get every file version for the zone.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class);
    }

    /**
     * Get the zone's Site mesh.
     */
    public function siteMesh(): HasOne
    {
        return $this->hasOne(FileVersion::class)->where('type', 'mesh')->where('is_official', true);
    }

    /**
     * Get the zone's Site doors file.
     */
    public function siteDoors(): HasOne
    {
        return $this->hasOne(FileVersion::class)->where('type', 'doors')->where('is_official', true);
    }

    /**
     * Get the approved community versions for the zone.
     */
    public function communityVersions(): HasMany
    {
        return $this->hasMany(FileVersion::class)
            ->where('status', FileVersion::STATUS_APPROVED)
            ->where('is_official', false);
    }

    /**
     * Get the Site file of the given type.
     */
    public function siteFile(string $type): ?FileVersion
    {
        return $type === 'doors' ? $this->siteDoors : $this->siteMesh;
    }

    /**
     * Narrow the query to zones matching a search term.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('short_name', 'like', "%{$term}%");
        });
    }

    /**
     * The first placeholder id for zones that have no EverQuest zone id.
     */
    public const FIRST_PLACEHOLDER_ID = 1111;

    /**
     * Give every zone with a blank or zero id the next free placeholder id,
     * counting up from 1111 in short name order and skipping ids in use.
     *
     * @return array<string, int> The ids given, by short name
     */
    public static function assignMissingIds(): array
    {
        $used = array_flip(static::whereNotNull('eq_zone_id')->pluck('eq_zone_id')->all());
        $next = self::FIRST_PLACEHOLDER_ID;
        $assigned = [];

        $missing = static::where(fn (Builder $query) => $query->whereNull('eq_zone_id')->orWhere('eq_zone_id', 0))
            ->orderBy('short_name')
            ->get();

        foreach ($missing as $zone) {
            while (isset($used[$next])) {
                $next++;
            }

            $zone->update(['eq_zone_id' => $next]);
            $used[$next] = true;
            $assigned[$zone->short_name] = $next;
        }

        return $assigned;
    }

    /**
     * Use the short name in URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'short_name';
    }
}
