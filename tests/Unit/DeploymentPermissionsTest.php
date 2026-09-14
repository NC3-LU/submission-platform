<?php

namespace Tests\Unit;

use Tests\TestCase;

class DeploymentPermissionsTest extends TestCase
{
    public function test_web_root_guard_restores_only_missing_traversal_permission(): void
    {
        $projectDirectory = sys_get_temp_dir().'/deployment-permissions-'.bin2hex(random_bytes(6));
        mkdir($projectDirectory.'/public', 0700, true);
        chmod($projectDirectory, 0700);

        try {
            $script = base_path('scripts/ensure-web-root-traversable.sh');
            exec('sh '.escapeshellarg($script).' '.escapeshellarg($projectDirectory), $output, $exitCode);

            clearstatcache(true, $projectDirectory);

            $this->assertSame(0, $exitCode, implode("\n", $output));
            $this->assertSame(0701, fileperms($projectDirectory) & 0777);
        } finally {
            rmdir($projectDirectory.'/public');
            rmdir($projectDirectory);
        }
    }
}
