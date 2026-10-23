<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expansion extends Model
{
    protected $fillable = [
        'code',
        'name',
        'year',
        'sort_order',
    ];

    /**
     * Get the zones that belong to the expansion.
     */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * Create or update every expansion listed in config/mqmesh.php.
     */
    public static function syncFromConfig(): void
    {
        foreach (config('mqmesh.expansions') as $order => $expansion) {
            static::updateOrCreate(
                ['code' => $expansion['code']],
                ['name' => $expansion['name'], 'year' => $expansion['year'], 'sort_order' => $order + 1],
            );
        }
    }

    /**
     * Use the expansion code in URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
