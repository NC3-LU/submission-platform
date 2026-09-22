"""Exercise runtime core limits and rendered Compose contracts without real data."""
import json
import os
from pathlib import Path
import resource
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class ContainerSafetyTest(unittest.TestCase):
    def test_entrypoint_disables_core_dumps_before_php_initialization_and_exec(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            binary = root / 'bin'
            binary.mkdir()
            # Stub only application setup. The entrypoint, shell resource limits,
            # filesystem setup and final exec are real.
            probe = ('#!' + sys.executable + '\nimport resource\n'
                     'assert resource.getrlimit(resource.RLIMIT_CORE) == (0, 0), '
                     '"runtime still permits crash dumps"\n')
            (binary / 'php').write_text(probe)
            (binary / 'chown').write_text('#!/bin/sh\nexit 0\n')
            (binary / 'runtime-check').write_text(probe)
            for path in binary.iterdir():
                path.chmod(0o700)
            result = subprocess.run(
                ['bash', str(ROOT / 'docker/entrypoint.sh'), str(binary / 'runtime-check')],
                cwd=root, env=dict(os.environ, PATH=str(binary) + ':' + os.environ['PATH']),
                preexec_fn=lambda: resource.setrlimit(resource.RLIMIT_CORE, (1048576, 1048576)),
                capture_output=True, text=True, timeout=10,
            )
            self.assertEqual(result.returncode, 0, result.stderr)

    def compose(self, filename):
        result = subprocess.run(
            ['docker', 'compose', '-f', str(ROOT / filename), '--profile', '*', 'config',
             '--format', 'json', '--no-interpolate', '--no-env-resolution'],
            cwd=ROOT, capture_output=True, text=True, check=True, timeout=15,
        )
        return json.loads(result.stdout)['services']

    def test_both_deployments_enforce_hard_and_soft_core_limits_for_every_service(self):
        for filename in ('docker-compose.yml', 'docker-compose.prod.yml'):
            services = self.compose(filename)
            for name in services:
                with self.subTest(compose=filename, service=name):
                    self.assertEqual(services[name].get('ulimits', {}).get('core'), {'soft': 0, 'hard': 0})

    def test_apache_has_http_readiness_and_the_xsavec_compatibility_default(self):
        app = self.compose('docker-compose.yml')['app']
        health = app.get('healthcheck', {})
        self.assertEqual(health.get('test'), ['CMD', 'curl', '--fail', '--silent', '--show-error',
                                           '--max-time', '5', 'http://127.0.0.1/up'])
        self.assertEqual(health.get('retries'), 3)
        self.assertIn('-XSAVEC', app['environment'].get('GLIBC_TUNABLES', ''))


if __name__ == '__main__':
    unittest.main()
