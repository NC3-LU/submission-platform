# Evidence for the 14 September 2026 assessment

These are assessment artifacts, not implementation changes. All dynamic security probes used synthetic data in a disposable copy, SQLite in memory, and fake private storage where files were involved. Browser fixtures ran on localhost with mail delivery disabled and Pandora disabled. No production credentials or customer data are included. Fixture, test and temporary resource labels are normalized in the retained text artifacts; measurements and outcomes are unchanged.

## Existing-project baseline

- `baseline-status.txt` / `baseline-diff.txt`: working-tree state before the audit. Commit was `3ad0f43`.
- `phpunit.log` / `phpunit.xml`: the existing 255-test suite, zero failures and four skips.
- `pint.log`, `composer-validate.log`, `platform-reqs.log`, `build.log`, and `migrate.log`: baseline checks.
- `composer-audit.json` / `npm-audit.json`: registry audit results as of the assessment.
- `secret-pattern-check.json`: limited tracked-file token/key-pattern check. Empty results do not establish that the project or its history contains no secrets.

## Targeted regression probes

`ProjectAuditProbeTest.php` describes **intended invariants**. It is outside the normal test suite because this is an evaluation, not a fix. On the assessed code, **13 of 14 tests fail**. Do not mistake that result for a failure of the existing 255-test suite or alter assertions merely to make these probes green.

The file guards its environment before application bootstrap. To replay it, use a **disposable checkout/copy with installed dependencies**, from that copy's root:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: \
  CACHE_STORE=array SESSION_DRIVER=array MAIL_MAILER=array \
  QUEUE_CONNECTION=sync PANDORA_ENABLED=false \
  php vendor/bin/phpunit --do-not-cache-result -c phpunit.xml \
  docs/audits/evidence/ProjectAuditProbeTest.php
```

Tests create/delete synthetic accounts, forms, submissions, and fake files. Keep production environment files, databases, and storage out of the disposable copy. `probe-results.log` and `probe-results.xml` retain the actual outcomes. The one passing probe checks that repeated attachment autosave retains a valid file reference. The checkbox/API invariant expresses the need for a multi-selection contract; a chosen documented canonical API representation may require adapting that test.

`middleware-order.json` shows Laravel's actual resolved middleware order and synthetic database settings. `http-rate-limit.json` records the valid-token HTTP check: an anonymous limit of 10 was applied, then HTTP 500 on request 11.

## Browser and accessibility evidence

`browser-audit.py` and `audit-seed.php` contain the local harness and synthetic fixtures used for the baseline. Their paths point to `/tmp/submission-platform-audit-2026-09-14`; adapt those paths for a new disposable run. They require Python Playwright, Chromium, an installed axe-core distribution, built application assets, an isolated migrated SQLite database, and a localhost server. The seed contains intentionally synthetic credentials; never run it in a real environment.

`browser-results.json` retains page status, overflow observations, axe node findings, and the tested flow results. The script is diagnostic and records defects; a successful script exit does not mean accessibility or behavior passed. The field-error result distinguishes the **visible generic summary** from the hidden invalid field/inline error. `browser-summary.log` is the compact output.

Historical screenshots are omitted. The JSON results retain the page, accessibility and validation findings; the harness can capture fresh screenshots in a disposable environment.

Axe ran WCAG 2 A/AA and WCAG 2.1 A/AA rules. Results count occurrences across repeated layouts. The sample is not a complete WCAG evaluation.

## Laravel 13 trial

- `laravel13-candidate-composer.json`: candidate requirements, not the project's active manifest.
- `laravel13-package-changes.json` and `upgrade-package-metadata.json`: the resolved changes and compatibility metadata.
- `laravel13-resolution.log` / `laravel13-adjusted-resolution.log`: initial blockers; the first is the pinned Livewire advisory, the next is Scramble 0.12 compatibility.
- `laravel13-final-resolution.log`: successful dependency dry run after adjusting requirements.
- `laravel13-phpunit.log` / `laravel13-phpunit.xml`: 255 tests, 601 assertions, four skips, zero failures.
- `laravel13-pint.log`, `laravel13-audit.json`, `laravel13-route-cache.log`, `laravel13-view-cache.log`: candidate checks.
- `laravel13-scramble-verbose.log`: model-inference warning in generated API docs.
- `laravel13-browser-results.json`: the repeated sample on Laravel 13; same page statuses and no JavaScript errors. The older diagnostic key `visible_errors` here counts only inline paragraph errors, not the global summary.
- `laravel13-probes.log`: same application invariants fail after the dependency upgrade. This run preceded formatting/environment-guard additions to the saved probe, so its line numbers refer to the earlier temporary file.

The disposable copies and servers are not part of the deliverable. The reports record remaining MySQL, production, scanner, mail, restoration, and complete browser-journey validation needs.
