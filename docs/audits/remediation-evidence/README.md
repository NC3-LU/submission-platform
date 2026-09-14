# Remediation verification — 14 September 2026

These results follow the fixes in [the remediation report](../2026-09-14-remediation.md). They are separate from the historical assessment's failing probes. Tests use synthetic data and isolated storage/databases. No real environment files, backup payloads or production secrets are included.

## Automated checks

- `phpunit-sqlite-final.log` and `phpunit-mysql-final.log`: complete current suites. Use `composer test` with the test configuration; MySQL requires a disposable test database because migrations/tests reset its contents. Existing environment variables take precedence over the defaults in `phpunit.xml`.
- `composer-validate-final.log`, `platform-final.log`, `composer-audit-final.json` and `npm-audit-final.json`: manifest/platform checks and registry audit results for the updated lockfiles.
- `pint-check-final.log`, `vite-final.log` and `browser-final.log`: formatting, asset build and browser/axe results. Replay with `composer lint`, `npm ci`, `npm run build`, `npx playwright install chromium`, then `npm run test:browser`. The browser harness creates a guarded disposable SQLite/storage environment; it never seeds the real project database. `PLAYWRIGHT_CHROMIUM_EXECUTABLE` optionally selects an already installed Chromium.
- `openapi-verified.log`: Scramble generation with a migrated disposable database, with no model-inference warnings. The API bearer scheme is configured in `AppServiceProvider`.
- `scanner-ownership-red.log` / `scanner-ownership-green.log`: a regression first reproduced an HTTP request for an attachment owned by another response, then passed with the owner-path guard. `maintenance-worker-red.log` records the deployment heartbeat regression before its fix; its passing case is included in the final full suites.

## Database and recovery

- `populated-upgrade.log` and `upgrade-manifest-before.json`: five legacy MySQL statuses and their existing answers survived the additive migrations with matching content hashes. The manifest contains counts/hashes, not answer text. The pre-upgrade fixture used the prior migration set in a disposable MySQL 8 database.
- `restore-final.log` / `restore-http-final.json`: an encrypted synthetic backup restored into fresh MySQL and storage volumes. The script verified archive checksums, core row/file hashes and HTTP readiness. A separate authenticated browser check verified login, response viewing, exact private attachment bytes, public header bytes and every entry in the built asset manifest. The attachment hash is of a synthetic fixture.
- `deployment-summary.json` records successful container builds, isolation/startup checks and the synthetic deploy/rollback rehearsal. These use local Apache containers and host-mounted public assets; the actual production host Apache/FPM/TLS path remains a staging acceptance requirement.

Scripts under `scripts/` and the [operations runbook](../../operations.md) describe replay and prerequisites. Backup encryption passphrases, decrypted snapshots, full private files and disposable database credentials are intentionally excluded. Temporary local resource names and image hashes identify the rehearsal only; they are not deployment targets.

The recorded recovery duration applies to a tiny fixture. It is not a recovery objective or a production-scale benchmark. Browser samples are not a complete accessibility assessment. Live mail/Pandora integration, organizational ownership and a successor's independent restore remain external handover gates.
