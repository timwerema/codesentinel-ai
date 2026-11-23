<?php

namespace App\Scanners;

interface Scanner
{
    public function name(): string;

    /**
     * Scan the files in $workspace (a throwaway directory holding only this analysis's files).
     * Must never throw: a tool that is missing, times out or prints rubbish returns ok=false.
     */
    public function scan(string $workspace): ScanResult;
}
