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
     * Use the short name in URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'short_name';
    }
}
