<?php

namespace App\Console\Commands;

use App\Services\OperationalHealth;
use Illuminate\Console\Command;

class HealthCheck extends Command
{
    protected $signature = 'app:health {--full : Include queue, scheduler and scanner checks}';

    protected $description = 'Check readiness; return a non-zero exit code when an operational dependency fails';

    public function handle(OperationalHealth $health): int
    {
        $checks = $health->checks((bool) $this->option('full'));
        $this->line(json_encode($checks, JSON_THROW_ON_ERROR));

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
