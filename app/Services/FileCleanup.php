<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Durable cleanup outbox: database rollback must never delete a response's attachment. */
final class FileCleanup
{
    public static function schedule(string $disk, array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            DB::table('pending_file_deletions')->insertOrIgnore(['disk' => $disk, 'path' => $path, 'created_at' => now()]);
        }
        DB::afterCommit(fn () => self::drain());
    }

    public static function drain(): void
    {
        DB::table('pending_file_deletions')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                try {
                    if (Storage::disk($row->disk)->delete($row->path)) {
                        DB::table('pending_file_deletions')->where('id', $row->id)->delete();
                    }
                } catch (Throwable $exception) {
                    report($exception); // Retain failed records for the next scheduled retry.
                }
            }
        });
    }
}
