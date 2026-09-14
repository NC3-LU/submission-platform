# Future production migration to Dokploy

The maintainer confirmed on 14 September 2026 that production deployments are currently performed manually on the server. Moving the application **and its database** to Dokploy is future work, separate from the unpublished v3.0.0 release. No production migration, deployment, database copy or DNS change has been performed by this release-preparation task.

## Inventory before scheduling the move

Confirm the canonical hostname first: the maintainer referred to `application.nc3.lu`, while the checked-in production compose references `applications.nc3.lu`. Record the current host, checkout path, release image, MySQL version, database size/collation, storage size, DNS/TLS owner, maintenance window and responsible operator.

The repository describes two existing topologies:

| Current production | Dokploy starting point |
| --- | --- |
| `docker-compose.prod.yml`, pinned project `applicationsnc3lu` | `docker-compose.yml`, self-contained Apache behind Traefik |
| PHP-FPM on host loopback port 9000; host Apache serves `public/` | Build the `runtime-apache` image and route its HTTP service through Dokploy |
| MySQL 8.0 in `applicationsnc3lu_dbdata` | New private MySQL service/database with independently verified persistence |
| `applicationsnc3lu_storage_data` plus the nested host `public/storage` bind | Persistent private storage and public header images shared by app, queue and scheduler |
| Application `.env`, `docker-compose.env`, host backup configuration | Protected Dokploy configuration with the same application encryption key and reviewed credentials |

Inventory every source of persistent data, including the nested public-header bind: copying only the general storage volume would omit those images. Preserve `APP_KEY`, which decrypts existing application data and webhook secrets. Inventory SMTP, Pandora, trusted proxies/hosts, session/cache configuration, API consumers and webhook receivers. Keep the source database port private.

## Rehearse in an isolated target

1. Create a new Dokploy project using `main` and a pinned reviewed release image. Use the Apache topology; do not carry the old host FPM/public-directory mounts into the new server layout.
2. Choose whether MySQL will be a Dokploy database service or a private compose service. Start with a compatible MySQL version; schedule a major database upgrade separately. Confirm persistence and backup destinations before restoring data. Dokploy documents both [compose deployment](https://docs.dokploy.com/docs/core/docker-compose) and [database management](https://docs.dokploy.com/docs/core/databases).
3. Use the existing encrypted logical SQL/files backup and restore-drill workflow against fresh target resources. Match table counts and content hashes, preserve character encoding and test database users/permissions.
4. Restore private attachments and public headers; verify the public storage link resolves only to intended public assets. Keep secrets outside images and repository files. Check app/queue/scheduler ownership and shared volume access.
5. Keep outgoing mail, scanning and webhooks disabled during rehearsal, and leave restored queued work stopped until reviewed. A copied queue can contain notifications and external deliveries. Avoid running old and new schedulers/workers against the same data.
6. Test login, MFA, form editing/duplication, representative submissions, attachments, scan gating, API tokens, private exports and the new queues on a staging hostname. Check `/up` and `app:health --full`, TLS/proxy headers, upload limits and signed URL/host behavior.
7. Configure database backups and file-volume backups, then restore both into another isolated target. Dokploy [volume backups](https://docs.dokploy.com/docs/core/volume-backups) cover named volumes; database recovery and any retained bind mounts need their own verified backup coverage. Record restore duration and operator access.

## Cutover and rollback

Schedule the cutover only after rehearsal evidence is reviewed. Keep the old environment and its last verified image/backup intact. Put the source into maintenance, stop and drain its workers/scheduler, take the final consistent encrypted SQL/files backup, restore the target and compare counts/hashes. Apply the reviewed migrations once, confirm storage and configuration, then switch routing/DNS and verify the target before reopening writes.

Before target writes are accepted, rollback can return routing to the unchanged source. After target writes begin, switching back to an older source database would lose those writes; stop writes and reconcile or restore target data before moving routing back. Record this decision point in the cutover checklist and keep one authoritative writable environment.

Acceptance: application and database run in Dokploy; attachments/headers and encryption-dependent features are intact; only one active worker/scheduler set processes the live queue; backup and restore are proven; monitoring and operator access work; rollback and old-server retirement have named owners. Retire old resources only after the agreed observation/retention period.
