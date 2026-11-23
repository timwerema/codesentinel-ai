<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AnalysisFile extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'language', 'size_bytes', 'line_count', 'sha256', 'storage_path'];

    protected $hidden = ['storage_path'];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    /** The stored source. Lives on the private disk: never under public/. */
    public function contents(): string
    {
        return (string) Storage::disk('local')->get($this->storage_path);
    }
}
