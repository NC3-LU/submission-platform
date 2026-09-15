# Running the platform

This page explains the service from an operator's point of view. The [operations runbook](https://github.com/NC3-LU/submission-platform/blob/main/docs/operations.md) contains the full deployment, rollback, backup, and restore procedures. Read it before changing a live installation.

## What needs to run

| Component | What it does |
| --- | --- |
| Web application | Serves forms, account pages, reviewer screens, and the API |
| MySQL | Stores accounts, form definitions, responses, and background-job records |
| Queue worker | Processes exports, scans, and enabled webhook deliveries |
| Scheduler | Runs cleanup, recovery, and service heartbeat tasks |
| Persistent storage | Holds private attachments and exports, public banners, and runtime files |
| Mail service | Delivers account verification and password-reset messages |
| Pandora, when enabled | Checks attachments for malware |

The web application, worker, and scheduler must use the same release and the appropriate shared database and storage. A working homepage alone does not show that background processing is healthy.

## Choose the deployment layout

| File | Layout |
| --- | --- |
| `docker-compose.yml` | Apache inside the application container, routed through the external `dokploy-network`; optional Pandora services use the `pandora` profile |
| `docker-compose.prod.yml` | PHP-FPM on `127.0.0.1:9000`, with host Apache serving `public/` and forwarding PHP requests |

The first layout needs its external network and reverse-proxy routing. It exposes container port 80 to its networks, not to a host browser port. The production layout needs a correctly configured host web server.

Production separates the application `.env` from `docker-compose.env`, which supplies Compose values. Match the database credentials across the relevant files. Preserve the existing Compose project name and storage volumes when upgrading.

## Configure an installation

Set the real application name and URL, a working mail service, database credentials, and the approved access policy. In production, use `APP_DEBUG=false`, HTTPS, secure session cookies, explicit trusted hosts, and the actual trusted proxy addresses.

Keep the existing `APP_KEY` and store its recovery copy securely. The database and persistent files are both needed for recovery. Expose only the application's `public/` directory through the web server.

The application supports two-factor authentication. Administrative account management is at `/admin`, with the verified `@nc3.lu` administrator restriction described in [Getting started](https://github.com/NC3-LU/submission-platform/wiki/Getting-Started).

## Enable attachment scanning

Pandora is optional. For a reachable scanner, the relevant application settings are:

```dotenv
PANDORA_ENABLED=true
PANDORA_URL=http://pandora:6100
PANDORA_TIMEOUT=30
PANDORA_POLL_INTERVAL=2
PANDORA_BLOCK_MALICIOUS=true
```

The example URL works only where `pandora` resolves to the scanner service. Enabling the setting does not itself start Pandora. Review the Compose profile and `scripts/setup-pandora.sh` for the scanner infrastructure used by this project.

With scanning and blocking enabled, downloads wait for an explicit clean verdict. A scanner outage or failed job leaves the file blocked. Keep the queue worker running and exercise the complete upload-to-download path in staging when changing scanner settings.

Existing submission files can be scanned with `php artisan app:scan-files --all`. This processes real files and may apply the configured malicious-file handling; plan it as an operational task.

## Check service health

```bash
php artisan app:health
php artisan app:health --full
php artisan queue:failed
```

Basic health checks cover the database, migrations, cache, and private storage; `/up` exposes readiness without internal details. Full health also checks queued work, failed jobs, cleanup, worker and scheduler heartbeats, and scanner reachability when enabled. A non-zero exit needs investigation.

Monitor disk space, queue delays, failed jobs, scanner failures, backup freshness, and certificate expiry. Fix the cause of a failed job before retrying its individual ID.

## Deploy, back up, and recover

`scripts/deploy.sh` builds a release, pauses writes, takes an encrypted backup, migrates, publishes assets, replaces services, and checks health. It needs `BACKUP_DIR`, `BACKUP_PASSPHRASE_FILE`, and `HEALTH_URL` configured as described in the runbook. A failed deployment can leave maintenance enabled for investigation.

`scripts/backup.sh` captures the database, persistent files, configuration, and release information. Expect a brief service interruption while writers are stopped. Protect the passphrase separately and keep off-host backup copies.

`scripts/rollback.sh` restores an earlier application image and its assets while preserving the database. `scripts/restore-drill.sh` checks recovery into fresh, isolated resources. A successful backup command is not a substitute for a restore drill.

Never use `docker compose down -v` on an installation whose data you need to retain. Follow the runbook for exact commands and recovery checks.

## Transfer forms when an owner leaves

Preview the transfer to an existing, verified evaluator or administrator:

```bash
php artisan app:transfer-form-ownership current-owner@example.test new-owner@example.test --dry-run
```

Check the listed forms, then run the same command without `--dry-run` to transfer ownership. Existing responses are preserved. Accounts that still own forms cannot be deleted; review the departing account's API tokens and integrations separately.

## Understand retention

The scheduler removes expired integration exports, old unreferenced temporary uploads, and untouched empty drafts under the configured rules. It does not automatically delete completed responses or answered drafts. The operating organization must define their retention period and an appropriate deletion process.

Background export files expire 24 hours after request. Webhook delivery history expires after seven days. Integration audit metadata has no automatic expiry. These technical defaults do not define the organization's overall records policy.

Next: [Operations runbook](https://github.com/NC3-LU/submission-platform/blob/main/docs/operations.md) · [Troubleshooting](https://github.com/NC3-LU/submission-platform/wiki/Troubleshooting)
