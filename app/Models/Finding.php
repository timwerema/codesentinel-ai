<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    use HasFactory;

    public const SEVERITIES = ['critical', 'high', 'medium', 'low', 'info'];

    public const CATEGORIES = ['security', 'secret', 'logic', 'maintainability', 'testing'];

    protected $fillable = ['analysis_file_id', 'source', 'verified', 'also_reported_by', 'rule_id', 'category', 'severity', 'title', 'explanation', 'remediation', 'suggested_code', 'line_start', 'line_end', 'fingerprint'];

    protected function casts(): array
    {
        return ['verified' => 'boolean', 'also_reported_by' => 'array'];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(AnalysisFile::class, 'analysis_file_id');
    }

    /** Lower number = more severe, for sorting. */
    public static function rank(string $severity): int
    {
        $i = array_search($severity, self::SEVERITIES, true);

        return $i === false ? count(self::SEVERITIES) : $i;
    }
}
