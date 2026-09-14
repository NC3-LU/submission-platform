# Project remediation and Laravel 13

Authorized by “fix all + upgrade”. Preserve all pre-existing local changes. Historical audit evidence remains in `docs/audits/`.

- [x] Reproduce audit findings as normal regression tests.
- [x] Install Laravel 13 and compatible patched PHP/npm dependencies.
- [x] Implement file ownership checks, fresh submission authorization, shared answer validation, API draft privacy, HTTP 429, export query reuse.
- [x] Guard account deletion, add ownership transfer, preserve responses on submitter deletion, lock used form structure, add data-preserving migration.
- [x] Finish regression coverage, status transitions, quotas/idempotency, transactional file lifecycle and concurrent schema mutation protection.
- [x] Fix UI feedback, accessibility, keyboard builder controls and pagination.
- [x] Make Docker builds isolated, asset delivery reproducible, startup fail closed; backup/restore/rollback and operational health tooling.
- [x] Add MySQL and browser CI; verify SQLite/MySQL migration and tests, build, audits, browser/axe and both Docker targets.
- [x] Update API/project/operations documentation and record remaining production validations.

Final outcomes and evidence: [remediation report](../audits/2026-09-14-remediation.md). Production rollout, organizational ownership, repository protection and the operational rehearsal remain explicit production readiness checks in that report.

Baseline snapshot: `/tmp/submission-platform-remediation-2026-09-14/baseline.tar.gz` and `baseline.patch` (excludes secrets/runtime data). Test and build logs are in the same temporary directory until final evidence is collected.

Product decisions: forms with responses must be duplicated to revise questions; account owners must transfer forms before deletion; removing a submitter anonymizes response ownership; other people’s drafts/ongoing responses stay private except administrator access.
