<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Bytes;

class FileVersion extends Model
{
    /** @use HasFactory<\Database\Factories\FileVersionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const SOURCE_DEPOT = 'depot';
    public const SOURCE_SEED = 'seed';
    public const SOURCE_UPLOAD = 'upload';

    protected $fillable = [
        'zone_id',
        'type',
        'version',
        'user_id',
        'source',
        'status',
        'is_official',
        'path',
        'original_name',
        'size',
        'md5',
        'sha256',
        'notes',
        'depot_commit',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'reviewed_at' => 'datetime',
            'size' => 'integer',
            'download_count' => 'integer',
        ];
    }

    /**
     * Get the zone the file belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the user who uploaded the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the moderator who reviewed the file.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the reports filed against the file.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Narrow the query to files anyone may see and download.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('is_official', true)->orWhere('status', self::STATUS_APPROVED);
        });
    }

    /**
     * Sort the Site file first, then newest first.
     */
    public function scopeSiteFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_official')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Whether anyone may see and download the file.
     */
    public function isPublic(): bool
    {
        return $this->is_official || $this->status === self::STATUS_APPROVED;
    }

    /**
     * Whether the given user may see and download the file.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->isPublic()) {
            return true;
        }

        return $user !== null && ($user->id === $this->user_id || $user->canModerate());
    }

    /**
     * The file name MQ2Nav expects on disk.
     */
    public function downloadName(): string
    {
        return sprintf(config("mqmesh.types.{$this->type}.download_name"), $this->zone->short_name);
    }

    /**
     * The file type's display label.
     */
    public function typeLabel(): string
    {
        return config("mqmesh.types.{$this->type}.label", ucfirst($this->type));
    }

    /**
     * The file size for display, such as 5.5 MB.
     */
    public function humanSize(): string
    {
        return Bytes::format($this->size);
    }

    /**
     * Who the file came from, for display.
     */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_DEPOT => 'mqmesh-depot',
            self::SOURCE_SEED => 'Original site',
            default => $this->user?->name ?? 'Deleted user',
        };
    }
}
