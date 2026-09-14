<?php

namespace App\Console\Commands;

use App\Models\SubmissionValues;
use App\Services\FileCleanup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneTemporaryUploads extends Command
{
    protected $signature = 'app:prune-temporary-uploads {--dry-run}';

    protected $description = 'Remove expired, unreferenced temporary uploads and retry pending file cleanup';

    public function handle(): int
    {
        $disk = Storage::disk('private');
        $before = now()->subHours(max(24, config('submissions.temporary_file_hours')))->timestamp;
        $count = 0;
        foreach ($disk->getDriver()->listContents('temp-submissions', true) as $item) {
            if (! $item->isFile() || $item->lastModified() >= $before || SubmissionValues::where('value', $item->path())->exists()) {
                continue;
            }
            if (! $this->option('dry-run')) {
                $disk->delete($item->path());
            }
            $count++;
        }
        if (! $this->option('dry-run')) {
            FileCleanup::drain();
        }
        $this->info($count.' expired temporary uploads'.($this->option('dry-run') ? ' would be removed.' : ' removed.'));

        return self::SUCCESS;
    }
}
