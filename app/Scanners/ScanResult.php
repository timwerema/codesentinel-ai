<?php

namespace App\Scanners;

final readonly class ScanResult
{
    /** @param  list<RawFinding>  $findings */
    public function __construct(
        public string $tool,
        public bool $ok,
        public array $findings = [],
        public ?string $version = null,
        public float $seconds = 0.0,
        public ?string $error = null,
    ) {}

    public function summary(): array
    {
        return ['status' => $this->ok ? 'ok' : 'failed', 'version' => $this->version, 'seconds' => round($this->seconds, 2), 'findings' => count($this->findings), 'error' => $this->error];
    }
}
