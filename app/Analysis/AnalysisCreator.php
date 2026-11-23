<?php

namespace App\Analysis;

use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Stores accepted source on the private disk, records the analysis and queues the work. */
class AnalysisCreator
{
    public function __construct(private SourceIntake $intake) {}

    /** @param  list<SourceFile>  $files */
    public function create(User $user, string $title, string $origin, array $files): Analysis
    {
        $this->intake->checkBatch($files);

        $analysis = DB::transaction(function () use ($user, $title, $origin, $files) {
            $analysis = $user->analyses()->create(['title' => $title, 'origin' => $origin, 'status' => Analysis::QUEUED]);

            foreach (array_values($files) as $i => $file) {
                // The stored path is built only from ids we generated. The user's file name is
                // kept for display in the database and never touches the filesystem.
                $path = "analyses/{$analysis->public_id}/{$i}.src";
                Storage::disk('local')->put($path, $file->contents);
                $analysis->files()->create([
                    'name' => $this->uniqueName($file->name, $i, $files),
                    'language' => $file->language,
                    'size_bytes' => $file->bytes(),
                    'line_count' => $file->lines(),
                    'sha256' => hash('sha256', $file->contents),
                    'storage_path' => $path,
                ]);
            }

            return $analysis;
        });

        RunAnalysis::dispatch($analysis->id);

        return $analysis;
    }

    /** Two uploads called utils.py become utils.py and utils-2.py, so findings map to one file. */
    private function uniqueName(string $name, int $index, array $files): string
    {
        $earlier = 0;
        foreach (array_values($files) as $i => $f) {
            if ($i < $index && $f->name === $name) {
                $earlier++;
            }
        }
        if ($earlier === 0) {
            return $name;
        }

        return pathinfo($name, PATHINFO_FILENAME).'-'.($earlier + 1).'.'.pathinfo($name, PATHINFO_EXTENSION);
    }
}
