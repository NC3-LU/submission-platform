<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => Cache::put('health:scheduler', now()->timestamp, 300))->everyMinute()->evenInMaintenanceMode();
Schedule::command('app:prune-temporary-uploads')->hourly()->withoutOverlapping();
// Only empty, untouched drafts are removed. Retention of completed responses is an organizational policy.
Schedule::command('app:prune-empty-drafts --force --days=30 --untouched-only')->daily()->withoutOverlapping();
Schedule::command('queue:prune-batches --hours=168')->daily()->withoutOverlapping();
