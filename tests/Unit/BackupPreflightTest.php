<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupPreflightTest extends TestCase
{
    public function test_invalid_database_credentials_cannot_interrupt_running_services(): void
    {
        $root = sys_get_temp_dir().'/backup-preflight-'.bin2hex(random_bytes(6));
        File::makeDirectory($root.'/bin', 0700, true);
        try {
            file_put_contents($root.'/passphrase', 'synthetic');
            file_put_contents($root.'/bin/docker', <<<'PY'
#!/usr/bin/env python3
import json, os, pathlib, sys
with (pathlib.Path(os.environ['BACKUP_TEST_ROOT'])/'calls.jsonl').open('a') as log:
    log.write(json.dumps(sys.argv[1:])+'\n')
sys.exit(78 if 'sh' in sys.argv else 0)
PY);
            file_put_contents($root.'/bin/gpg', "#!/bin/sh\nexit 0\n");
            chmod($root.'/bin/docker', 0700);
            chmod($root.'/bin/gpg', 0700);
            $process = new Process(['bash', base_path('scripts/backup.sh')], base_path(), [
                'PATH' => $root.'/bin:'.getenv('PATH'),
                'BACKUP_TEST_ROOT' => $root,
                'BACKUP_DIR' => $root.'/backups',
                'BACKUP_PASSPHRASE_FILE' => $root.'/passphrase',
            ]);
            $process->setTimeout(10)->run();
            $this->assertNotSame(0, $process->getExitCode());
            $calls = array_map(fn ($line) => json_decode($line, true), file($root.'/calls.jsonl', FILE_IGNORE_NEW_LINES));
            foreach ($calls as $call) {
                $this->assertEmpty(array_intersect(['down', 'stop', 'start', 'up'], $call), 'Failed database preflight must leave services untouched.');
            }
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_missing_encryption_passphrase_cannot_interrupt_running_services(): void
    {
        $root = sys_get_temp_dir().'/backup-preflight-'.bin2hex(random_bytes(6));
        File::makeDirectory($root.'/bin', 0700, true);
        try {
            file_put_contents($root.'/bin/docker', "#!/bin/sh\ntouch \"\$BACKUP_TEST_ROOT/docker-called\"\nexit 78\n");
            file_put_contents($root.'/bin/gpg', "#!/bin/sh\nexit 0\n");
            chmod($root.'/bin/docker', 0700);
            chmod($root.'/bin/gpg', 0700);
            $process = new Process(['bash', base_path('scripts/backup.sh')], base_path(), [
                'PATH' => $root.'/bin:'.getenv('PATH'),
                'BACKUP_TEST_ROOT' => $root,
                'BACKUP_DIR' => $root.'/backups',
                'BACKUP_PASSPHRASE_FILE' => $root.'/missing-passphrase',
                'KEEP_MAINTENANCE' => '1',
            ]);
            $process->setTimeout(10)->run();
            $this->assertNotSame(0, $process->getExitCode());
            $this->assertFileDoesNotExist($root.'/docker-called', 'Backup preflight must fail before any service operation.');
        } finally {
            File::deleteDirectory($root);
        }
    }
}
