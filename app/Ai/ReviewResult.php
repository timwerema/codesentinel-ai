<?php

namespace App\Ai;

use App\Scanners\RawFinding;

final readonly class ReviewResult
{
    /**
     * @param  list<RawFinding>  $suggestions  always source "ai", verified false
     * @param  array<string, mixed>  $usage
     */
    public function __construct(public string $mode, public string $summary, public array $suggestions, public array $usage = []) {}
}
