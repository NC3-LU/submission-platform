# API additions and release preparation

Release scope: the Laravel 13 upgrade, project remediation, issues #49 and #47, and the API additions in #57–#65. Prepare an unpublished release draft for review after implementation and verification.

## Work sequence

- [x] #57 authenticated token context and self-revocation; retain/deprecate `/api/user` compatibly.
- [x] #58 scoped, filtered and bounded token lifecycle history.
- [x] #60 atomic bounded batch token revocation, explicit caller preservation and stricter throttling.
- [x] #65 canonical plural form access-link routes with a documented temporary legacy alias.
- [x] #49 readable, authorized Pandora verdict details with unchanged scan/download policy.
- [x] #59 authorized API file downloads and safe file metadata; shared download policy.
- [x] #61 independent transactional form duplication with managed asset copying.
- [x] #62 owner-controlled collaborator APIs, explicit sharing ability and audit trail.
- [x] #63 private asynchronous JSON/XLSX exports with limits, authorization, expiry and audits.
- [x] #64 opt-in signed webhooks, endpoint ownership, SSRF-safe delivery, bounded retries and audits.
- [x] #47 update branch references, merge the verified changes, rename the GitHub default branch to `main`, verify PR targets and CI; document external deployment configuration requirements.
- [x] Complete API/OpenAPI docs, regression/browser/MySQL/build/audit/container checks and release changelog.
- [x] Commit and push verified changes, then prepare an unpublished release draft for review.

## Integration notes

- GitHub CI for `dev` commit `4dd61e1` passed every applicable job: SQLite/MySQL, browser/accessibility, frontend, and both container builds (run 34842564604).
- Latest remote `master` is `397dd7d`. Its only change beyond the audited base is Dependabot's Tailwind 3 → 4 manifest update, without the corresponding PostCSS migration. An isolated build reproduces the failure. Preserve the tested Tailwind 3 toolchain when integrating this release; keep the reason explicit in the merge/changelog.
- Existing API v1 authentication uses the application's hashed `ApiToken` records and IP/ability middleware. `/api/user` is a separate Sanctum compatibility endpoint; the new context routes must not bypass the established v1 controls or silently remove existing authentication support.
- Raw issue bodies and the master-build reproduction are saved in `/tmp/submission-platform-release-2026-09-14/` (no production credentials).
- Use disposable databases/storage and fake external deliveries for tests. No live customer webhook/email/scanner request is part of verification.

## Incremental verification

- Scan detail collection/view and existing scan/download behavior: 31 tests, 93 assertions.
- API downloads and shared web gate: new file endpoint tests pass; regression suite includes scoped IDs, safe headers, path privacy, warning/block modes.
- Form duplication plus existing web clone/header behavior: 27 tests, 97 assertions. Conditional field references are remapped; safe managed images are independent; copy failures roll back records and copied files.
- Collaborator management: 6 tests, 32 assertions (owner/admin sharing authority, bounded membership, audit rollback, no unrelated-account enumeration, ability delegation).
- Asynchronous exports: 7 tests, 61 assertions (123-row JSON, private XLSX literal strings, policy rechecks, quotas, size limits, expiry and cleanup).
- Production deployment is manual on the server. There is no external branch-triggered pipeline to change. Server checkout migration to `main` is documented; future application + database migration to Dokploy is separate planned work (`docs/plans/dokploy-migration.md`).
- Webhook feature/destination/response-limit tests pass. Concurrent MySQL regression reproduced and fixed a webhook quota/account lock inversion using a dedicated quota lock row; batch revocation deletes/audits each target once under concurrent calls. The concurrency probe is added to MySQL CI.
- Intermediate full SQLite and MySQL suites passed 374 tests / 1,155 assertions (four existing skips), before the two added response-limit unit tests. Four browser/axe workflows pass, including keyboard scan details at desktop/mobile sizes. Composer/npm audits report no vulnerabilities.

- Final local verification: 376 PHP tests, 1,162 SQLite / 1,165 MySQL assertions, four existing skips; standalone MySQL concurrency probe passes; four browser/axe workflows pass; Composer/npm audits clear; PHP formatting and route cache pass; OpenAPI exports without warnings (29 paths, 48 unique operations); Apache/FPM image builds and isolation checks pass.

## Completed release preparation

- Implemented issues #49 and #57–#65; committed as `6270942`, integrated with the former default branch as `176e97c` without changing the verified tree.
- Pushed the verified commit to both `dev` and `main`. GitHub renamed the default branch and automatically retargeted open PR #68 to `main`; the old remote branch is gone. Local upstreams and `origin/HEAD` follow the new name. Production deployment remains manual, with the server checkout update documented for its next deployment.
- GitHub CI passed all applicable jobs on [dev](https://github.com/NC3-LU/submission-platform/actions/runs/34848690034) and [main](https://github.com/NC3-LU/submission-platform/actions/runs/34849007437), including the MySQL concurrency probe. Dependency review is correctly skipped on push. GitHub reports no open dependency alerts after the default-branch update.
- Prepared the [unpublished v3.0.0 draft](https://github.com/NC3-LU/submission-platform/releases/tag/untagged-0dfff96e45b468b8b0b5) with release notes and `openapi.json`. Publication remains subject to review; no production deployment or Dokploy migration was performed.
- Removed the disposable MySQL test container and its anonymous volume. Test logs and build artifacts remain under `/tmp/submission-platform-release-2026-09-14/`; production data/configuration were not used.
- A later browser CI run caught reduced error-text contrast during the section fade when validation focused a hidden field. A deterministic regression reproduced zero effective opacity at focus; revealed sections and conditional fields now appear immediately. All four browser workflows pass locally with the focus regression in place. Final commit and CI results are recorded in the release draft.
