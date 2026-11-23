<?php

namespace App\Ai;

use App\Scanners\RawFinding;

final readonly class ReviewRequest
{
    /**
     * @param  list<array{name: string, language: string, contents: string}>  $files
     * @param  list<RawFinding>  $scannerFindings
     */
    public function __construct(public array $files, public array $scannerFindings) {}
}
