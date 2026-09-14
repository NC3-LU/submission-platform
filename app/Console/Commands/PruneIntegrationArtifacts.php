<?php

namespace App\Console\Commands;

use App\Services\SubmissionExports;
use Illuminate\Console\Command;

class PruneIntegrationArtifacts extends Command
{
    protected $signature = 'app:prune-integration-artifacts';

    protected $description = 'Expire private integration exports and remove their artifacts';

    public function handle(SubmissionExports $exports): int
    {
        $exports->prune();
        $this->info('Integration artifacts pruned.');

        return self::SUCCESS;
    }
}
