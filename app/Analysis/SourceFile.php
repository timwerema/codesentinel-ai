<?php

namespace App\Analysis;

/** One accepted source file, before it is stored. */
final readonly class SourceFile
{
    public function __construct(public string $name, public string $language, public string $contents) {}

    public function bytes(): int
    {
        return strlen($this->contents);
    }

    public function lines(): int
    {
        return $this->contents === '' ? 0 : substr_count($this->contents, "\n") + (str_ends_with($this->contents, "\n") ? 0 : 1);
    }
}
