<?php

namespace Tests\Unit;

use Dotenv\Dotenv;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RestoreIsolationTest extends TestCase
{
    public function test_restore_cannot_inherit_production_connections_or_outbound_integrations(): void
    {
        $root = sys_get_temp_dir().'/restore-isolation-'.bin2hex(random_bytes(6));
        File::makeDirectory($root.'/bin', 0700, true);
        File::makeDirectory($root.'/snapshot', 0700, true);
        $source = <<<'ENV'
APP_KEY="base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="
DB_URL=mysql://synthetic:synthetic@production.invalid/live
DB_HOST=production.invalid
DB_SOCKET=/production/mysql.sock
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=sqs
DB_QUEUE_CONNECTION=production
INTEGRATION_QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=dynamodb
FILESYSTEM_DISK=s3
LOG_CHANNEL=slack
MAIL_MAILER=smtp
MAIL_LOG_CHANNEL=slack
PANDORA_ENABLED=true
WEBHOOKS_ENABLED=true
RUN_MIGRATIONS=true
ENV;
        try {
            foreach (['database.sql', 'storage.tar.gz', 'public.tar.gz', 'compose.env', 'data-manifest.json', 'images.json', 'revision.txt'] as $name) {
                file_put_contents($root.'/snapshot/'.$name, 'synthetic');
            }
            file_put_contents($root.'/snapshot/app.env', $source);
            // These archive contents are used only to reach the migration boundary.
            (new Process(['tar', '-czf', $root.'/snapshot/public.tar.gz', '--files-from', '/dev/null']))->mustRun();
            $checksums = '';
            foreach (File::files($root.'/snapshot') as $file) {
                $checksums .= hash_file('sha256', $file->getPathname()).'  '.$file->getFilename()."\n";
            }
            file_put_contents($root.'/snapshot/SHA256SUMS', $checksums);
            (new Process(['tar', '-C', $root.'/snapshot', '-cf', $root.'/backup.tar', '.']))->mustRun();
            file_put_contents($root.'/passphrase', 'synthetic');
            file_put_contents($root.'/bin/gpg', "#!/bin/sh\nfor argument do last=\"\$argument\"; done\ncat \"\$last\"\n");
            file_put_contents($root.'/bin/docker', <<<'PY'
#!/usr/bin/env python3
import json, os, pathlib, sys
args = sys.argv[1:]
root = pathlib.Path(os.environ['RESTORE_TEST_ROOT'])
if args[0] == 'inspect' or args[:2] in [['volume', 'inspect'], ['network', 'inspect']]:
    sys.exit(1)
if args[:2] == ['network', 'create']:
    (root/'network.json').write_text(json.dumps(args))
if args[0] == 'exec':
    sys.stdin.read()
if args[0] == 'run' and args[-3:] == ['artisan', 'migrate', '--force']:
    env = {}
    for line in pathlib.Path(args[args.index('--env-file') + 1]).read_text().splitlines():
        if line and not line.startswith('#'):
            key, value = line.split('=', 1)
            env[key] = value
    mounts = [args[i+1] for i, arg in enumerate(args) if arg == '-v']
    (root/'migration.json').write_text(json.dumps({'env': env, 'mounts': mounts}))
    sys.exit(78)  # Never run a database, application or migration in this test.
PY);
            chmod($root.'/bin/gpg', 0700);
            chmod($root.'/bin/docker', 0700);
            $process = new Process(['bash', base_path('scripts/restore-drill.sh'), $root.'/backup.tar'], base_path(), [
                'PATH' => $root.'/bin:'.getenv('PATH'),
                'RESTORE_TEST_ROOT' => $root,
                'RESTORE_DIR' => $root.'/restored',
                'RESTORE_PROJECT' => 'submission-restore-fixture',
                'RESTORE_IMAGE' => 'synthetic:never-started',
                'BACKUP_PASSPHRASE_FILE' => $root.'/passphrase',
            ]);
            $process->setTimeout(10)->run();
            $this->assertSame(78, $process->getExitCode(), $process->getErrorOutput());
            $migration = json_decode(file_get_contents($root.'/migration.json'), true, 512, JSON_THROW_ON_ERROR);
            // Docker environment overrides take precedence over Laravel's parsed .env.
            $effective = array_replace(Dotenv::parse($source), $migration['env']);
            $connection = (new ConfigurationUrlParser)->parseConfiguration([
                'driver' => $effective['DB_CONNECTION'], 'url' => $effective['DB_URL'] ?? null,
                'host' => $effective['DB_HOST'], 'database' => $effective['DB_DATABASE'],
            ]);
            $this->assertSame('submission-restore-fixture-db', $connection['host']);
            $this->assertSame('restored', $connection['database']);
            foreach ([
                'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file',
                'QUEUE_CONNECTION' => 'database', 'DB_QUEUE_CONNECTION' => 'mysql',
                'INTEGRATION_QUEUE_CONNECTION' => 'database', 'QUEUE_FAILED_DRIVER' => 'database-uuids',
                'FILESYSTEM_DISK' => 'local', 'LOG_CHANNEL' => 'single', 'MAIL_MAILER' => 'log',
                'MAIL_LOG_CHANNEL' => 'single', 'PANDORA_ENABLED' => 'false', 'WEBHOOKS_ENABLED' => 'false',
                'RUN_MIGRATIONS' => 'false', 'APP_KEY' => Dotenv::parse($source)['APP_KEY'],
            ] as $key => $expected) {
                $this->assertSame($expected, $effective[$key], $key);
            }
            $this->assertContains($root.'/restored/app.env:/var/www/html/.env:ro', $migration['mounts']);
            $this->assertContains('--internal', json_decode(file_get_contents($root.'/network.json'), true));
        } finally {
            File::deleteDirectory($root);
        }
    }
}
