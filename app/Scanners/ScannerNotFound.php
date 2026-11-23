<?php

namespace App\Scanners;

use RuntimeException;

class ScannerNotFound extends RuntimeException
{
    public function __construct(public readonly string $binary)
    {
        parent::__construct("Scanner program not found: {$binary}");
    }
}
