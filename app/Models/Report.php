<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'file_version_id',
        'user_id',
        'reason',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the reported file.
     */
    public function fileVersion(): BelongsTo
    {
        return $this->belongsTo(FileVersion::class);
    }

    /**
     * Get the user who filed the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Narrow the query to reports nobody has handled yet.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
