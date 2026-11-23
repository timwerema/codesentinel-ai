<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analysis extends Model
{
    use HasFactory, HasUlids;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = ['title', 'origin', 'status', 'error', 'scanners', 'ai_mode', 'ai_usage', 'ai_summary', 'is_demo', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['scanners' => 'array', 'ai_usage' => 'array', 'is_demo' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** Only the public id is a ULID; the primary key stays a plain integer. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AnalysisFile::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED], true);
    }
}
