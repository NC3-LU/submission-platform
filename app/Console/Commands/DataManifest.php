<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataManifest extends Command
{
    protected $signature = 'app:data-manifest {--verify= : Compare against a backup manifest}';

    protected $description = 'Emit counts and content hashes for checking database/file backup integrity (no answer contents)';

    public function handle(): int
    {
        $manifest = ['tables' => [], 'files' => []];
        foreach (['users', 'forms', 'form_categories', 'form_fields', 'submissions', 'submission_values'] as $table) {
            $hash = hash_init('sha256');
            $count = 0;
            foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
                $data = (array) $row;
                // New nullable migration columns do not change the meaning of a restored record.
                $data = array_filter($data, fn ($value) => $value !== null);
                ksort($data);
                hash_update($hash, json_encode($data, JSON_THROW_ON_ERROR));
                $count++;
            }
            $manifest['tables'][$table] = ['count' => $count, 'sha256' => hash_final($hash)];
        }
        foreach (['private', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);
            $paths = $disk->allFiles();
            sort($paths);
            $hash = hash_init('sha256');
            foreach ($paths as $path) {
                hash_update($hash, $path);
                $stream = $disk->readStream($path);
                hash_update_stream($hash, $stream);
                fclose($stream);
            }
            $manifest['files'][$diskName] = ['count' => count($paths), 'sha256' => hash_final($hash)];
        }
        if ($this->option('verify')) {
            $expected = json_decode(file_get_contents($this->option('verify')), true, 512, JSON_THROW_ON_ERROR);
            if ($manifest !== $expected) {
                $this->error('Restored data does not match the backup manifest.');

                return self::FAILURE;
            }
            $this->info('Restored database and file contents match the backup manifest.');
        } else {
            $this->line(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }
}
