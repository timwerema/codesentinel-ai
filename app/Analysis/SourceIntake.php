<?php

namespace App\Analysis;

use Illuminate\Support\Str;

/**
 * The gate every submitted file passes through. It decides what is accepted and gives each
 * file a safe display name. The name is never used to build a path on disk.
 */
class SourceIntake
{
    /** @return array<string, string> extension => language */
    public function languages(): array
    {
        return config('codesentinel.languages');
    }

    public function accept(string $originalName, string $contents): SourceFile
    {
        $name = $this->safeName($originalName);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $language = $this->languages()[$extension] ?? null;

        if ($language === null) {
            throw new RejectedSource("\"{$name}\" is not a supported file type. Supported: .".implode(', .', array_keys($this->languages())).'.');
        }
        if ($contents === '' || trim($contents) === '') {
            throw new RejectedSource("\"{$name}\" is empty.");
        }
        $max = config('codesentinel.limits.max_file_bytes');
        if (strlen($contents) > $max) {
            throw new RejectedSource("\"{$name}\" is larger than the ".round($max / 1024).' KB limit per file.');
        }
        // Source code is text. A NUL byte or invalid UTF-8 means a binary (or a disguised one).
        if (str_contains($contents, "\0") || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new RejectedSource("\"{$name}\" does not look like a text source file.");
        }

        return new SourceFile($name, $language, str_replace(["\r\n", "\r"], "\n", $contents));
    }

    /** @param  list<SourceFile>  $files */
    public function checkBatch(array $files): void
    {
        $limits = config('codesentinel.limits');
        if ($files === []) {
            throw new RejectedSource('Add some code to analyse.');
        }
        if (count($files) > $limits['max_files']) {
            throw new RejectedSource("At most {$limits['max_files']} files per analysis.");
        }
        if (array_sum(array_map(fn (SourceFile $f) => $f->bytes(), $files)) > $limits['max_total_bytes']) {
            throw new RejectedSource('The files together are larger than the '.round($limits['max_total_bytes'] / 1024).' KB limit per analysis.');
        }
    }

    /**
     * Keep only the base name, drop anything that is not a plain filename character, and never
     * let it start with a dot or a dash (hidden files, things that look like command options).
     */
    public function safeName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? '';
        // Split first, then clean the stem, so a name made only of stripped characters keeps its extension.
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $stem = $extension === '' ? $name : substr($name, 0, -strlen($extension) - 1);
        $stem = Str::limit(trim(ltrim($stem, '.-_'), '.'), 80, '');

        if ($stem === '' || trim($stem, '_') === '') {
            $stem = 'file';
        }

        return $extension === '' ? $stem : "{$stem}.{$extension}";
    }
}
