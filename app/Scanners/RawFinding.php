<?php

namespace App\Scanners;

/** A finding in CodeSentinel's own shape, whatever tool reported it. */
final readonly class RawFinding
{
    /** @param  list<string>  $alsoReportedBy */
    public function __construct(
        public string $source,
        public bool $verified,
        public string $file,
        public ?int $lineStart,
        public ?int $lineEnd,
        public string $ruleId,
        public string $category,
        public string $severity,
        public string $title,
        public string $explanation,
        public ?string $remediation = null,
        public ?string $suggestedCode = null,
        public array $alsoReportedBy = [],
    ) {}

    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
