# Operations

This release upgrades the application to Laravel 13, PHP 8.3+, Livewire 3.8 and compatible Filament 3/Scramble versions. Use Node 24 LTS for builds. Dependencies are installed from committed lockfiles; production asset publication extracts the actual image assets. See the [official Laravel upgrade guide](https://laravel.com/docs/13.x/upgrade) and [Node support schedule](https://nodejs.org/en/about/previous-releases).

## Topologies and configuration

`docker-compose.yml` runs a self-contained Apache application behind the external `dokploy-network`; create that network before local use. Its optional `pandora` profile includes the scanner stack. `docker-compose.prod.yml` is a standalone production definition with PHP-FPM bound to `127.0.0.1:9000` and host Apache serving the host `public/` directory. Keep the pinned compose project and existing `dbdata`/`storage_data` volume names. Never use `down -v` for an existing environment.

Production host Apache must serve only `public/`, deny access to hidden files and proxy PHP to the loopback FPM endpoint using the container path `/var/www/html/public/index.php`. Verify HTTPS, redirects, request-size limits and cache headers on the actual host. Application `.env` is mounted read-only; `docker-compose.env` supplies compose database/build values. Neither secrets nor runtime storage/caches belong in image layers. Database, mail, encryption and backup keys must live in the organization's secret store, with operator access verified.

Set `APP_DEBUG=false`, the actual `APP_URL`, secure session cookies, explicit trusted hosts and only the real proxy addresses. Proxy trust must match the deployed network so IP restrictions and quotas use the intended client IP. Keep the existing `APP_KEY`: changing it invalidates encrypted application data, sessions and two-factor secrets. Explicit existing cache prefixes and cookie names continue to apply across Laravel 13.

## Build, deploy and roll back

Build both relevant targets before release:

```bash
docker build --target runtime-fpm -t submission-platform:release-fpm .
docker build --target runtime-apache -t submission-platform:release-apache .
```

The entrypoint creates runtime directories, applies restricted private-file permissions, runs migrations only when `RUN_MIGRATIONS=true`, builds caches and checks application dependencies. A failed migration or readiness check stops startup. Production deployment owns migrations; app, queue and scheduler have automatic migration disabled there.

Run deployment from the production project root with `BACKUP_DIR`, `BACKUP_PASSPHRASE_FILE` and `HEALTH_URL=https://your-host/up` configured in the operator environment. The passphrase file must be protected and its recovery copy stored separately from backups. `bash scripts/deploy.sh` builds first, enters maintenance, stops writers, takes an encrypted snapshot, migrates once, publishes image assets, replaces app/workers, checks dependencies and verifies the host HTTP endpoint. It preserves volumes and prints the previous image for rollback. Failure keeps maintenance enabled. Scanner infrastructure must already be running. Review and resolve existing failed jobs before deployment because full health intentionally fails on them.

`RELEASE_IMAGE` optionally selects the new image tag; `.release-image` records the successfully deployed tag without credentials. Source archives do not require a Git checkout or host PHP: set `RELEASE_REVISION` to the full reviewed commit SHA. Deployment records it in `.release-revision`; asset validation runs inside the image. Backups of older archives without revision metadata record `unknown` and retain the actual container image IDs. Before building, deployment pins the current image under a separate `submission-platform:pre-deploy-TIMESTAMP` tag so reusing a release tag cannot discard the rollback image. Retain that tag through the agreed rollback window. Scripts use `.release-image` through `scripts/compose-common.sh`. When invoking `docker compose` directly, export that `APP_IMAGE` first so compose does not select its default tag accidentally.

For a code rollback, run `HEALTH_URL=... bash scripts/rollback.sh PREVIOUS_IMAGE`. Set `ROLLBACK_REVISION` when the previous commit is known; otherwise its revision is recorded as `unknown`. This restores the previous image and its assets while retaining the database. The new migrations expand the status domain and preserve account constraints; application rollback does not require destructive schema rollback. Verify login, an ordinary submission and a known attachment after rollback. The previous image may not contain the new health/scheduler commands, so the script uses its migration-status and HTTP checks and leaves scheduler review explicit. A data-corruption recovery requires a reviewed backup restore, not `migrate:rollback`.

## Encrypted backups and isolated restore drills

`bash scripts/backup.sh` requires GnuPG and the same `BACKUP_DIR` and `BACKUP_PASSPHRASE_FILE`. It rejects a missing, empty or unreadable passphrase file and checks database authentication before changing services. Dumps and database health checks use the configured database owner (`MYSQL_USER`/`MYSQL_PASSWORD`), whose database privileges must cover tables, views, routines, triggers and events; they do not depend on a root bootstrap password still matching an existing volume. It enables maintenance and stops HTTP, queue and scheduler processes with a 240-second shutdown allowance to keep SQL, private files, public headers and release assets consistent. Expect a brief service interruption; a normal standalone backup restarts those services afterwards. It captures a MySQL logical dump, storage archives, environment configuration including the encryption key, revision/image information and content-hash manifests. Files are encrypted with GnuPG/AES256 and an integrity-protected archive; temporary plaintext is confined to a private directory and removed afterwards. Keep backup storage outside the project/web root. Restrict access and arrange off-host copies and expiry under the organization's retention policy.

Schedule backups through the approved operations system. Choose the frequency and retention from an agreed recovery point objective; no organizational target is assumed here. Check backup exit codes, recency, off-host replication and access to the separate decryption secret. A successful archive command alone is not proof of recovery.

Restore only into fresh, isolated resources for a drill:

```bash
export RESTORE_PROJECT=submission-restore-review
export RESTORE_DIR=/protected/new-restore-directory
export RESTORE_IMAGE=submission-platform:release-apache
export RESTORE_DB_IMAGE=mysql:8.0.36-debian
export BACKUP_PASSPHRASE_FILE=/protected/backup-passphrase
bash scripts/restore-drill.sh /protected/backups/submission-TIMESTAMP.tar.gpg
```

Choose a restore database image compatible with the production version and host CPU. `RESTORE_DB_IMAGE` defaults to the pinned production image above; floating MySQL images can require newer CPU instruction sets.

The drill refuses existing resource names/directories, verifies archive checksums, starts a new isolated MySQL database, restores files, applies the current additive migrations and compares counts/content hashes. It clears database URL/socket overrides, uses local cache/session/queue/logging settings and disables outbound mail, scanning and webhooks. The original environment file is mounted read-only so Laravel correctly parses quoted encryption keys. Restored queued jobs are not started.

The containers use an internal Docker network without an outbound route or published host ports. Run the drill on the Docker host; the displayed private address is reachable there. For remote browser verification, forward a local port through SSH, for example `ssh -N -L 8767:RESTORE_PRIVATE_IP:80 user@restore-host`, then open `http://localhost:8767`. Keep its secrets and restored personal data protected. Verify login, form structure, representative responses and downloads; record elapsed recovery time. Application startup should also pass `/up` before signing off. Scanner-specific downloads require a separate controlled scanner-enabled staging exercise.

After reviewing the drill, remove only its explicitly named containers, volumes, network and protected directory. Never substitute production resource names. Secure disposal of temporary plaintext and backup retention remain the infrastructure owner's responsibility; deleting a file is not a guarantee of physical-media erasure.

## Queue, scanner and maintenance

Run `queue:work --tries=3 --sleep=3 --timeout=180 --max-time=3600` and `schedule:work` from the same release as the application. Worker services explicitly use SIGTERM and a 240-second shutdown grace period, including when their base image is Apache. The database queue's default `retry_after` is 240 seconds, longer than the worker timeout, as required by the [Laravel queue guidance](https://laravel.com/docs/13.x/queues). Check any existing environment override when upgrading.

`php artisan app:health` checks the database, migration state, cache and private storage. `/up` uses these readiness checks without exposing internal details. `php artisan app:health --full` additionally checks old queued work, failed jobs, pending file cleanup, queue/scheduler heartbeats and scanner HTTP reachability when enabled. Run full health from the monitoring system and alert the operations owner on a non-zero exit. A reachable scanner home page does not prove end-to-end scanning: exercise a clean fixture, a standard antivirus test fixture and a simulated outage on isolated staging, checking pending/failed download blocking and recovery.

Hourly maintenance removes unreferenced temporary uploads older than the configured period (48 hours by default) and retries committed file deletions. Daily maintenance removes only untouched empty drafts older than 30 days and old queue batch metadata. Completed responses, answered drafts and failed jobs are not silently pruned. Investigate failed jobs with `queue:failed`, correct the cause, then retry individual IDs; use `queue:forget ID` only after resolving or deliberately accepting that failure. Do not blindly retry every restored job, which can repeat notifications or external actions.

Monitor disk capacity, oldest queued-job age, failed scans/jobs, scheduler/worker heartbeat, failed deployments, certificate expiry, backup freshness and restore-drill results. Ensure monitoring notifications reach the responsible operations contact. `security.txt` must be renewed before its expiry.

## Account and form ownership

First inventory form owners, assigned evaluators, API-token owners/integrations and infrastructure/secret-store access. Transfer ownership using verified existing evaluator or administrator accounts:

```bash
php artisan app:transfer-form-ownership current-owner@example.test new-owner@example.test --dry-run
php artisan app:transfer-form-ownership current-owner@example.test new-owner@example.test
```

The command preserves forms and answers. Account deletion is blocked while it owns forms, including administrative deletion. Deleting a submitter removes the account link while preserving response contents; this is not automatic erasure of personal data inside answers. Tokens and access assignments require their own review: issue replacement integration credentials to the appropriate long-term owner, verify the integration, then revoke the previous owner's credentials and remove obsolete assignments. Never transfer plaintext personal tokens between people.

Forms with responses have immutable question structures. Duplicate the form to create a revised version; deliberately deleting a whole form remains destructive and removes its responses and owned attachments.

Before production rollout, assign a product owner, technical maintainer, operations owner and backup contact; verify their repository/CI, deployment, DNS/TLS, mail, scanning, backup and secret-store access. Have an operator perform a real approved backup restore and a staging deploy/rollback. Confirm branch protection and required CI checks on the repository host. The responsible organizational owner must approve form-specific privacy/retention periods and public legal/licence statements. These external ownership and production validations cannot be established by local automated tests.

### Repository protection checked on 14 September 2026

The initial audit found that `NC3-LU/submission-platform` is public, the original default branch `master` had no classic branch protection. Its only visible repository/organization ruleset is disabled. These were read-only API checks; no repository policy has been changed.

[branch-protection.json](branch-protection.json) is a proposed configuration requiring the new CI checks, one approving reviewer and resolved conversations, with force pushes/deletion disabled. Enable it after these workflows have been merged and the organization has confirmed who can review and handle emergencies. Applying the new check names before they exist would block unrelated merges. An authorized administrator can review the JSON and apply it with:

```bash
gh api --method PUT repos/NC3-LU/submission-platform/branches/main/protection --input docs/branch-protection.json
```

This is an outstanding repository-setting action, separate from the local code remediation. Recheck the returned policy and a sample pull request before marking it complete.


### Default branch migration and integration jobs

Issue #47 renames the repository default branch to `main`. Update existing local clones after the rename:

```bash
git branch -m master main
git fetch origin
git branch -u origin/main main
git remote set-head origin -a
```

Use `main` for production checkout/deployment targets and `dev` for ongoing development. GitHub automatically retargets PRs when its branch-rename operation runs. CI watches `main` and `dev`. Production deployment is manual on the server using source archives; there is no Git checkout or external branch-triggered pipeline to update. Export the reviewed commit from `main`, retain the existing environment files and persistent data, and pass its full SHA as `RELEASE_REVISION`. For environments that use Git checkouts, run the clone commands above and verify the branch and upstream both name `main`. A future move of the application and database to Dokploy is documented in [the migration plan](plans/dokploy-migration.md).

The normal worker processes exports and webhooks on the default queue using `INTEGRATION_QUEUE_CONNECTION=database`. Do not configure a synchronous driver for integration jobs. `app:prune-integration-artifacts` runs hourly; webhook recovery runs every minute. Review failed/stalled jobs and `integration_events` for quota, delivery and export failures. Temporary exports expire after 24 hours; webhook delivery history lasts seven days. Integrations use the private storage disk, and the current export writer requires its local filesystem driver. Keep the private disk outside the public web root. Webhooks default to `WEBHOOKS_ENABLED=false`; follow the signature, deduplication, limits and destination contract in [api.md](api.md) when enabling them.
