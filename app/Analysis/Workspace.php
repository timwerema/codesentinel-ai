<?php

namespace App\Analysis;

use App\Models\Analysis;
use Illuminate\Support\Facades\File;

/**
 * A throwaway directory holding a copy of one analysis's files for the scanners:
 *   <tmp>/src/<name>   read-only copies
 *   <tmp>/home         empty, writable: the scanners' HOME and cache
 * Removed when the analysis finishes, whatever happens.
 */
class Workspace
{
    private function __construct(public readonly string $path) {}

    public static function for(Analysis $analysis): self
    {
        $path = sys_get_temp_dir().'/codesentinel-'.$analysis->public_id.'-'.bin2hex(random_bytes(6));
        File::makeDirectory($path.'/src', 0755, true);
        File::makeDirectory($path.'/home', 0777, true);
        chmod($path, 0755);
        chmod($path.'/home', 0777); // the scanner may run as another (unprivileged) user

        foreach ($analysis->files as $file) {
            $target = $path.'/src/'.$file->name; // already sanitized and unique per analysis
            File::put($target, $file->contents());
            chmod($target, 0444);
        }

        return new self($path);
    }

    public function destroy(): void
    {
        // Files the scanner created as another user are removable because the directory is ours.
        @chmod($this->path.'/home', 0777);
        File::deleteDirectory($this->path);
    }
}
