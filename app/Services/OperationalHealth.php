<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class OperationalHealth
{
    /** @return array<string, bool> */
    public function checks(bool $full = false): array
    {
        $checks = [];
        $check = function (string $name, callable $callback) use (&$checks) {
            try {
                $checks[$name] = (bool) $callback();
            } catch (Throwable) {
                $checks[$name] = false;
            }
        };
        $check('database', fn () => DB::select('SELECT 1') !== []);
        $check('schema', function () {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));

            return array_diff(array_keys($files), $migrator->getRepository()->getRan()) === [];
        });
        $check('cache', function () {
            $key = 'health:'.Str::uuid();
            Cache::put($key, 'ok', 30);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ok;
        });
        $check('private_storage', function () {
            $path = '.health/'.Str::uuid();
            $disk = Storage::disk('private');
            $ok = $disk->put($path, 'ok') && $disk->get($path) === 'ok';

            return $disk->delete($path) && $ok;
        });
        if ($full) {
            $check('queue_age', fn () => ! DB::table('jobs')->where('available_at', '<', now()->subMinutes(5)->timestamp)->exists());
            $check('failed_jobs', fn () => ! DB::table('failed_jobs')->exists());
            $check('file_cleanup', fn () => ! DB::table('pending_file_deletions')->where('created_at', '<', now()->subHour())->exists());
            $check('queue_heartbeat', fn () => Cache::get('health:queue', 0) >= now()->subMinutes(3)->timestamp);
            $check('scheduler_heartbeat', fn () => Cache::get('health:scheduler', 0) >= now()->subMinutes(3)->timestamp);
            if (config('services.pandora.enabled')) {
                $check('scanner_reachable', fn () => Http::withOptions(['proxy' => ''])->timeout(5)->get(rtrim(config('services.pandora.url'), '/'))->successful());
            }
        }

        return $checks;
    }
}
