<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentArtifactTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/deployment-artifact-'.bin2hex(random_bytes(6));
        foreach (['bin', 'project/scripts', 'project/public/storage', 'image/build', 'image/storage'] as $path) {
            File::makeDirectory($this->root.'/'.$path, 0700, true);
        }
        copy(base_path('scripts/compose-common.sh'), $this->root.'/project/scripts/compose-common.sh');
        file_put_contents($this->root.'/project/.env', 'APP_ENV=testing');
        file_put_contents($this->root.'/project/docker-compose.env', 'DB_DATABASE=synthetic');
        file_put_contents($this->root.'/passphrase', 'synthetic');
        file_put_contents($this->root.'/project/public/storage/keep.txt', 'existing upload');
        file_put_contents($this->root.'/image/build/app.js', 'new asset');
        file_put_contents($this->root.'/image/build/manifest.json', '{"app":{"file":"app.js"}}');
        file_put_contents($this->root.'/image/storage/keep.txt', 'must not overwrite uploads');
        file_put_contents($this->root.'/bin/docker', <<<'PY'
#!/usr/bin/env python3
import os, pathlib, shutil, subprocess, sys
args = sys.argv[1:]
if args[0] == 'compose':
    args = args[5:]
root = pathlib.Path(os.environ['ARTIFACT_TEST_ROOT'])
if args[0] == 'create':
    print('synthetic-container')
elif args[0] == 'cp':
    shutil.copytree(root/'image', args[-1], dirs_exist_ok=True)
elif args[0] == 'run':
    if '-r' in args:
        sys.exit(subprocess.run([os.environ['TEST_PHP_BINARY'], '-r', args[args.index('-r')+1], str(root/'image')]).returncode)
    elif 'app:data-manifest' in args:
        print('{}')
    else:
        print('synthetic storage archive')
elif args[0] == 'images':
    print('[]')
elif args[0] == 'exec' and 'sh' in args:
    if 'MYSQL_ROOT_PASSWORD' in args[-1]:
        sys.exit(78)
    print('synthetic SQL')
PY);
        file_put_contents($this->root.'/bin/gpg', <<<'PY'
#!/usr/bin/env python3
import pathlib, sys
pathlib.Path(sys.argv[sys.argv.index('--output')+1]).write_bytes(sys.stdin.buffer.read())
PY);
        file_put_contents($this->root.'/bin/git', "#!/bin/sh\nexit 128\n");
        file_put_contents($this->root.'/bin/php', "#!/bin/sh\nexit 77\n");
        foreach (['docker', 'gpg', 'git', 'php'] as $name) {
            chmod($this->root.'/bin/'.$name, 0700);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function runScript(string $name, array $arguments = []): Process
    {
        $process = new Process(['bash', base_path('scripts/'.$name), ...$arguments], $this->root.'/project', [
            'PATH' => $this->root.'/bin:'.getenv('PATH'),
            'ARTIFACT_TEST_ROOT' => $this->root,
            'TEST_PHP_BINARY' => PHP_BINARY,
            'BACKUP_DIR' => $this->root.'/backups',
            'BACKUP_PASSPHRASE_FILE' => $this->root.'/passphrase',
            'KEEP_MAINTENANCE' => '1',
        ]);
        $process->setTimeout(10)->run();

        return $process;
    }

    public function test_asset_publication_uses_image_php_and_preserves_uploads(): void
    {
        $process = $this->runScript('publish-assets.sh', ['synthetic:image', $this->root.'/project/public']);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('new asset', file_get_contents($this->root.'/project/public/build/app.js'));
        $this->assertSame('existing upload', file_get_contents($this->root.'/project/public/storage/keep.txt'));
    }

    public function test_backup_of_a_source_artifact_uses_database_owner_without_git_metadata(): void
    {
        $process = $this->runScript('backup.sh');
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $archives = glob($this->root.'/backups/*.tar.gpg');
        $this->assertCount(1, $archives);
        $metadata = new Process(['tar', '-xOf', $archives[0], './revision.txt']);
        $metadata->mustRun();
        $this->assertSame('unknown', trim($metadata->getOutput()));
    }
}
