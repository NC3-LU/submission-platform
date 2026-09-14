# Project remediation and Laravel 13 upgrade

**14 September 2026 — local implementation and verification.** This follows the [original assessment](2026-09-14-project-assessment.md). Historical assessment files describe the earlier state. The existing uncommitted API/security work was preserved. No production database, deployment or real application environment file was changed.

The application now uses **Laravel 13.31.0**, PHP **8.3+**, Livewire **3.8.8**, Scramble **0.13.43**, PHPUnit **12.5.35** and Vite **8.3.0**. Filament stays on the compatible 3.x line. CI and Docker use Node 24; local browser/build verification used Node 26.8.1. Dependency lockfiles are updated, and Composer/npm report no known advisories as of this check. See the [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade).

## Findings addressed

| Finding | Implemented behavior and evidence |
| --- | --- |
| S1 — file ownership | Upload persistence requires a server-recorded path belonging to that field and submission. Traversal, backslashes and foreign references are rejected. Scanner jobs also reject legacy foreign references before reading or sending a file. Regression tests preserve the victim attachment. |
| S2 — stale authorization | Every browser mutation checks fresh form visibility, availability, private access link, ownership and submission status. Form/submission locks protect writes; stale drafts cannot overwrite submitted answers. |
| D1 — account deletion | Owners must transfer forms before deleting their account. A dry-run-capable ownership transfer command preserves answers. Database constraints block owner cascades; deleting a submitter unlinks the account while retaining the response. |
| D2 — historical structure | Question/category structure is immutable once responses exist. Direct model edits and builder/web mutations are guarded. Duplicate a used form to revise its questions. Whole-form deletion remains deliberately destructive. |
| S3 — draft privacy | Other people's draft/ongoing responses are excluded from evaluator lists and denied through API, web and download policies. Administrators retain explicit access. Bulk exports exclude unfinished responses. |
| S4 — dependencies | Compatible Laravel 13 dependencies and npm lockfile updates remove all advisories reported by the audit. Registry checks are date-specific, not a guarantee against unknown vulnerabilities. |
| O1 — release assets | Deployment publishes and verifies the assets extracted from the selected image, preserving prior hashed files for rollback. Browser HTTP checks cover real asset delivery. |
| O2 — recovery | Added encrypted, consistent backups with stopped writers; isolated restore and content-manifest verification; deployment and code rollback scripts. Startup rejects unavailable dependencies. Synthetic recovery includes login and private/public file checks. Real production recovery remains an external gate below. |
| S5 — image contents | Runtime storage, environment files and generated caches are excluded from builds. Both image targets contain built assets and no application environment file or private runtime files. Generated bootstrap cache files are removed from Git tracking. |
| D3 — status domain | Centralized supported statuses and review-only API transitions. An additive migration replaces the narrow MySQL enum without resetting statuses/answers. All statuses round-trip on SQLite and MySQL. |
| F1 — answer contract | Browser/API share field, conditional, required-checkbox, option and file validation. Checkbox selections retain their comma-separated stored/exported representation; JSON no longer casts them to a boolean. |
| F2 — throttling | Token authentication precedes token-aware throttling; HTTP exceptions preserve status and headers, including 429 and retry guidance. Route regression coverage runs in both database suites. |
| F3 — file lifecycle | Deletion uses a durable transaction-aware cleanup outbox. Rollback retains attachments; committed cleanup is retried. API form/submission deletion no longer loses file references before cleanup. |
| U1 — workflow feedback | Validation opens/focuses the first invalid step, conditional fields update reactively, guest draft guidance is accurate, save failures are visible, preview routing works, and receipts survive background Livewire requests. |
| U2 — accessibility | Shared contrast, names, labels, group semantics, skip link, focus/reduced-motion behavior and mobile cookie layout improved. Builder controls support keyboard reordering/moving. Browser tests assert no axe violations on sampled pages. |
| O3 — operations | Queue retry budget exceeds worker timeout. Added scheduler/queue heartbeats, full health checks, temporary-upload cleanup and safe empty-draft maintenance. Failed jobs and answered/completed responses are not silently purged. |
| PERF1 — queries | JSON export reuses the form schema, with a regression assertion against queries growing per response. Dashboard/My Forms are paginated and relevant submission indexes added. No production capacity claim is made. |
| M1 — documentation | Updated setup/version/topology guidance, explicit API contract and bearer OpenAPI scheme, operational runbook, ownership transfer, privacy description, licence metadata and security contact expiry. Organizational policy approval remains outstanding. |
| M2 — test boundaries | Added normal regression tests, SQLite/MySQL CI, browser/axe CI and both container builds. Public write quotas and browser replay protection are enforced. Proposed GitHub protection settings are reviewable; applying them remains outstanding. |

## Verification

| Check | Result |
| --- | --- |
| SQLite PHPUnit | **285 tests, 664 assertions, four skipped**, no failures; 14.226 seconds. |
| MySQL 8 PHPUnit | **285 tests, 670 assertions, four skipped**, no failures; 51.702 seconds. |
| Populated MySQL upgrade | Five legacy statuses with existing answers migrated successfully; before/after row and file content hashes match. |
| Browser / accessibility | **Three workflows passed**, 35.4 seconds. Desktop/mobile, light/dark public pages, conditional/required answers, focus, receipts, dashboard, locked structure, keyboard builder and administrator pages. Sampled axe checks report zero violations. |
| Dependencies / formatting | Composer validation, platform requirements, audit, npm audit and Pint pass. |
| Frontend build | Vite production build passes. Main CSS 129.05 kB / 20.51 kB gzip; JS 51.12 kB / 9.70 kB gzip. Separately served framework assets/fonts are additional. |
| OpenAPI | Generation against a migrated disposable database succeeds without model-inference warnings and declares bearer security. |
| Containers | Both PHP-FPM and Apache targets build. Image isolation checks pass; missing-database startup exits unsuccessfully. |
| Backup / restore | Encrypted synthetic backup restores into new MySQL/storage resources. Checksums, content manifest, HTTP readiness, login, response view, private download, public header and image assets pass. Restore script elapsed time: **50.27 seconds** for this small fixture, not a production recovery estimate. |
| Deployment / code rollback | Synthetic Apache deployment passes full health and HTTP checks. Rollback also succeeds with the app initially stopped; restored code/assets retain the database and file content manifest, and authenticated download checks pass. This rehearsal switches between local Laravel 13 images; rollback to the actual previous production release still requires staging validation. |

The four existing skips are three disabled Jetstream API-token feature tests and the registration-disabled branch while registration is enabled. The custom API-token tests run. No coverage percentage, screen-reader certification, cross-browser guarantee or realistic-load result is claimed.

Reproducible browser fixtures and checks are in `tests/Browser/`, `scripts/browser-server.sh` and `playwright.config.js`. Retained verification logs are in [remediation-evidence](remediation-evidence/README.md). The original audit's failing probes have become passing normal tests in `tests/Feature/RemediationTest.php`, with additional data-integrity, contract, recovery and scanner coverage.

## Release behavior to review

Use the [operations runbook](../operations.md) for deployment, backup, rollback and ownership transfer, and [API contract](../api.md) for integrations.

- Preserve the current `APP_KEY`, database/storage volumes and integration ownership. Back up before applying the new migrations. The protective migration intentionally does not restore destructive account cascades or narrow the status domain on rollback.
- Used form question structures must be revised by duplicating the form. Removing a submitter's account preserves answers, which can still contain personal data.
- Browser duplicate submissions use a locked request key. The API has no new idempotency-header contract. Quotas default to 10 writes/minute and 100/day per form and actor; anonymous actors share an IP bucket. Review those defaults for shared-network events.
- Backups briefly stop the HTTP/queue/scheduler services for consistency. Full deployment health rejects unresolved failed jobs and stale worker/scheduler heartbeats. Review these operational changes before rollout.
- Two generated `bootstrap/cache` files are staged for removal from version control; their local generated copies remain ignored. Other changes remain available for normal review and commit.

## Production readiness checks

These are not marked complete by local code/tests:

1. Merge/release the reviewed changes and run CI on GitHub. After the new checks exist, enable the proposed [branch protection](../branch-protection.json). A read-only API check found `master` unprotected and the only visible ruleset disabled.
2. Have an operator deploy and roll back on the actual staging topology, verify host Apache/FPM routing, HTTPS, trusted hosts/proxies, secure cookies and production-sized migrations.
3. Restore a real approved backup independently, including private files, public images and encryption keys. Agree recovery time/data-loss targets and verify off-host copies and decryption-secret access.
4. Demonstrate real mail delivery and Pandora clean/malicious/unavailable outcomes on controlled staging, including queue retries, download blocking and operator alerts. Local scanner tests use HTTP/notification fakes.
5. Assign product, technical, operations and backup owners; transfer forms and integration credentials; verify repository/hosting/DNS/TLS/mail/scanner/secret-store access and monitoring destinations.
6. Obtain organizational approval for privacy/retention and licensing statements. Complete manual accessibility and representative load checks appropriate to actual usage.

Local remediation is ready for review; the operational and organizational checks above determine production readiness.
