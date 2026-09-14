# Submission Platform: handover assessment

Assessment date: **14 September 2026**. Departure context: **31 October 2026**.

**Verdict: a useful, tested application with several security and data-integrity defects that should be resolved before handover.** A rewrite is not indicated. Protect stored submissions, make authorization consistent, and prove recovery and deployment before spending time on architectural restructuring.

**Offboarding dependency:** transfer form ownership before deleting the departing maintainer's application account. Account deletion currently cascades to owned forms and other users' submissions (D1).

The [Laravel 13 assessment](2026-09-14-laravel-13-assessment.md) includes a successful isolated upgrade trial. It does not replace the fixes below.

## Scope and confidence

Reviewed the working tree at commit `3ad0f43`, including the existing uncommitted API/security changes: 51 status entries at the start, some representing whole untracked directories. These changes were preserved. Application code, dependency manifests, and the working project's configuration were not changed by this assessment.

Work included code and configuration review; the existing automated suite; additional probes using synthetic data; fresh SQLite migrations; dependency audits; asset compilation; authenticated and anonymous browser journeys; accessibility checks; and an isolated Laravel 13 installation.

Browser checks used Chromium at 1440×1000 and a 390×844 mobile viewport. Seven page/viewport samples covered the homepage, login, public form, dashboard, builder, and Filament admin. The browser sample is not a complete manual accessibility audit or end-to-end acceptance suite.

**Not established:** production's deployed revision, TLS/proxy settings, actual secrets handling, infrastructure access, backups or successful restores, MySQL execution, live email delivery, Pandora/ClamAV integration, realistic load behavior, other browsers, or recovery from real service failures. No production application or production database was tested. A limited tracked-file secret-pattern check found no matches; Git history and comprehensive secret scanning remain outside this assessment.

## Readiness by area

| Area | Assessment | Main evidence / remaining need |
| --- | --- | --- |
| Core functionality | Needs fixes | Submission, access, and export tests exist; checkbox semantics, status consistency, and preview behavior need attention. |
| Security | Urgent fixes | Cross-submission file move reproduced; action authorization and draft confidentiality differ between entry points. |
| Data integrity | Urgent fixes | Owner and field deletion remove historical records; stale drafts can overwrite submitted answers. |
| UI / UX | Needs targeted fixes | Responsive sample works; generic errors, misleading guest draft action, and preview routing impede tasks. |
| Accessibility | Needs fixes | 29 failing node occurrences across seven samples; repeated components are counted more than once. |
| Performance | Partly assessed | Small frontend bundles and streamed exports; measured query amplification in JSON export. No capacity claim. |
| Tests / CI | Good foundation, important gaps | 255 tests, 601 assertions, four skips; no failing existing tests. SQLite-only CI misses production-specific contracts. |
| Deployment / operations | Needs rehearsal | Production asset delivery, migration failure behavior, queues, restoration, and monitoring need verification. |
| Documentation / ownership | Incomplete for handover | Setup and feature documentation exist; ownership transfer and operations runbooks are missing. |
| Framework lifecycle | Upgrade feasible | Laravel 13.31.0 trial passes existing suite and sampled browser checks; see separate assessment. |

## Verification results

| Check | Result |
| --- | --- |
| Existing PHPUnit suite | 255 tests, 601 assertions, four skipped, zero failures; 10.538 s locally. |
| Existing Pint check | Passed. |
| Composer validation and platform requirements | Passed; validation warns about exact Livewire version pin. |
| Fresh SQLite migrations | Passed in disposable application copy. |
| Vite production build | Passed on local Node 26.8.1. CSS 89.49 kB / 14.33 kB gzip; JS 51.13 kB / 9.70 kB gzip. This excludes separately served Livewire/Filament assets and fonts. CI's Node 20 was not exercised locally. |
| PHP dependency audit | 13 advisories across three locked packages: nine high and four medium. |
| npm audit | Three affected packages: one high, one moderate, one low. |
| Targeted audit probes | 14 tests, 20 assertions: 13 fail their intended invariants; repeated attachment autosave passes. These are separate from the existing 255 tests. |
| Browser sample | All seven page samples returned 200; no page JavaScript errors; no horizontal overflow in the sampled layouts. Conditional select visibility worked. |
| API throttling over HTTP | With a valid token, requests used the anonymous limit of 10; request 11 returned 500 without Retry-After. |
| Generated OpenAPI | Exports; current document has no bearer security scheme. Scramble 0.13 additionally reports a model-inference warning. |
| Laravel 13 trial | Same 255 tests / 601 assertions / four skips; no existing-suite failures. PHP audit clear. See separate report for limits. |

The four skips are three disabled Jetstream API-token feature tests and the registration-disabled branch while registration is enabled. The custom API-token test suites do run. No coverage percentage was measured.

Evidence and replay instructions are in [evidence/README.md](evidence/README.md).

## Priority findings

Priority indicates recommended sequencing, not a formal CVSS score. **P0:** address immediately. **P1:** resolve before handover. **P2:** schedule during handover preparation, with an owner for anything deferred.

### S1 · P0 · Forged upload paths can move another submission's file

**Reproduced.** [SubmissionForm.php](../../app/Livewire/SubmissionForm.php#L798) checks ownership when saving ordinary values, but `handleFileUploads()` independently accepts any file-field string beginning with `temp-submissions/`. It then moves that path and writes the new reference without consulting the locked upload-path map.

The synthetic guest probe submitted a path beginning with `temp-submissions/../submissions/` and referencing a known file belonging to a different submission. The victim file disappeared from its original location. Storage normalizes the relative path. An attacker needs a valid form component with a file field and knowledge of the target path; the probe does not establish a way to discover arbitrary unknown paths. Moving the file can break the victim's download and attach its contents to the attacker's submission. Scanning does not enforce ownership.

**Fix / acceptance:** accept only server-recorded uploads for the specific field and current actor; reject traversal and foreign paths before any filesystem operation. Check move success. The supplied cross-submission probe must pass with the original file unchanged and no unauthorized reference created. Cover both submit and draft persistence, with scanning enabled and disabled.

### S2 · P1 · Submission actions do not consistently reauthorize current state

**Three cases reproduced.** [SubmissionForm.php](../../app/Livewire/SubmissionForm.php#L448) persists drafts without checking current edit authorization or status. `submit()` gates availability on the public, unlocked `isEditMode` property at line 679 and does not recheck current visibility/entitlement.

- Setting `isEditMode=true` on an existing component allows submission after the availability window closes.
- A guest with an already open public form can submit after that form becomes private.
- A component holding a draft can save new answers after another request has marked that submission submitted.

**Fix / acceptance:** authorize each mutation against fresh persisted state; derive edit eligibility on the server; verify current access-link validity where relevant; enforce status transitions inside the persistence transaction. Test expired/revoked links, changed roles/assignments, closed/unpublished forms, stale tabs, and post-submit autosave. [Livewire's security guidance](https://livewire.laravel.com/docs/3.x/security) explains why public properties and actions require this treatment.

### D1 · P1 · Deleting a form owner deletes other users' submissions

**Reproduced.** [DeleteUser.php](../../app/Actions/Jetstream/DeleteUser.php#L16) directly deletes the user. The [forms foreign key](../../database/migrations/2024_10_08_141558_create_forms_table.php#L25) cascades deletion through forms and submissions. The admin editor also exposes a user delete action. There is no documented ownership-transfer process.

**Fix / acceptance:** implement and rehearse ownership transfer or deactivation before offboarding; block destructive deletion while the account owns retained records. Verify another maintainer can administer transferred forms and tokens and that submission counts, values, files, and scan records remain intact. Address account deletion through both self-service and admin paths.

### D2 · P1 · Editing a used form can destroy historical answers

**Reproduced for field deletion.** [FormFieldManager.php](../../app/Livewire/FormFieldManager.php#L217) deletes fields/categories directly; the web field deletion route also deletes them. Submission values reference fields with cascading deletion. Removing a field from a used form removed its already submitted answer in the probe. Exports also derive labels and options from today's form structure rather than a submitted schema snapshot.

**Fix / acceptance:** define a policy for published form changes. Preserve schema versions or retire used fields instead of deleting them. Verify existing submissions retain their answers and original meaning after field deletion, renaming, option edits, and category changes. Use new migrations rather than rewriting historical migrations.

### S3 · P1 · Draft confidentiality differs between web and API paths

**Reproduced.** [SubmissionPolicy.php](../../app/Policies/SubmissionPolicy.php#L31) denies an appointed evaluator access to someone else's draft. [API SubmissionController.php](../../app/Http/Controllers/Api/SubmissionController.php#L198) permits that evaluator to retrieve the draft and its answers with `submissions:read`. API listing likewise does not exclude drafts. Bulk exports and the submission index also warrant alignment because they query drafts without per-record filtering.

**Fix / acceptance:** document the intended role × ownership × status matrix and apply it to views, lists, API endpoints, downloads, and exports. An API ability must not bypass the record-level rule. Preserve any deliberate draft-review policy consistently and disclose it to submitters.

### S4 · P1 · Locked dependencies now have security advisories

**Registry audit evidence, not demonstrated application exploits.** The current lock contains affected `guzzlehttp/guzzle`, `league/commonmark`, and `livewire/livewire` versions. Livewire is pinned exactly to 3.6.4. The frontend audit reports `nanoid`, `postcss`, and `postcss-selector-parser`, all in the development/build dependency tree.

Reachability varies: form descriptions use the application's escaping Markdown helper; CommonMark extension advisories do not automatically establish an exploitable form-description endpoint. Guzzle is used for the configured Pandora service. The reported Livewire issue affects browser state handling. See the [Livewire advisory](https://github.com/livewire/livewire/security/advisories/GHSA-g3hc-697w-wm82) and [Guzzle advisory](https://github.com/guzzle/guzzle/security/advisories/GHSA-v5mv-p594-2x33); the saved audit JSON contains every advisory link.

**Fix / acceptance:** refresh reviewed lockfiles, rerun audits and behavior checks, and establish a named owner for Dependabot updates. Both current CI audit gates would fail against these results. The isolated Laravel 13 dependency set has no PHP advisories, but that does not repair application-level findings or npm dependencies.

### O1 · P1 · Production mounts hide the assets built into the image

**Configuration finding; production deployment not exercised.** [Dockerfile](../../Dockerfile#L115) copies built assets into image `public/build`. [docker-compose.prod.yml](../../docker-compose.prod.yml#L77) then bind-mounts host `./public` over the entire image directory. [deploy.sh](../../scripts/deploy.sh) builds images but does not populate the host's ignored `public/build` directory. A fresh host checkout therefore lacks those assets; an existing host can retain stale assets. No longer-present deployment workflow should be assumed to fill this gap.

**Fix / acceptance:** define one versioned release artifact and explicitly deliver matching public assets to the host, or serve them from the built image. Rehearse deployment from a clean checkout and verify the rendered manifest, CSS/JS responses, and application revision agree. Include the host Apache/FastCGI configuration in the runbook.

### O2 · P1 · Recovery cannot be handed over from the repository alone

**Documentation and configuration gap; existing external backups may exist.** The deployment script tells operators to restore from backup but supplies neither a backup procedure nor a tested restore/rollback procedure. The [entrypoint](../../docker/entrypoint.sh#L49) also allows migration failure to continue; the deployment script subsequently retries migration, but a direct container restart can run an outdated schema. `/up` is application liveness, with no additional database, queue, or scanner health listeners found.

**Fix / acceptance:** a successor must independently restore a disposable environment from a real approved backup, including DB, private files, public header images, and the relevant encryption key. Record recovery time and tolerated data loss, backup locations/access, application version, integrity checks, and rollback steps. Verify failed migrations and broken dependencies fail a deployment health gate. Do not treat preservation of Docker volume names as backup evidence.

### S5 · P1 · Docker build context can include runtime data

**Configuration finding.** [.dockerignore](../../.dockerignore) excludes environment files but not `storage/`. `COPY . .` carries the build context into the Composer stage and then the runtime image. Private files, logs, or cached runtime configuration present on a build host can therefore enter image layers even if runtime volumes later hide them.

**Fix / acceptance:** exclude runtime data and generated caches from the build context; create only empty writable directories in the image. Inspect a disposable build containing a synthetic sentinel upload/log and confirm no sentinel is present in image layers. Whether any historical image actually contains sensitive data remains unverified.

### D3 · P1 · API statuses disagree with the MySQL schema

**Source-confirmed mismatch; MySQL behavior not executed.** The API accepts `processing` and `rejected` in [status updates](../../app/Http/Controllers/Api/SubmissionController.php#L235), while the [database enum](../../database/migrations/2024_12_03_151628_submission_status_change.php#L22) contains `draft`, `ongoing`, `submitted`, `under_review`, and `completed`. API filters also advertise `approved`. SQLite tests do not establish that these writes work under production MySQL enum enforcement.

**Fix / acceptance:** agree the lifecycle and centralize valid statuses/transitions. Align schema, APIs, policies, UI, and exports. Add a MySQL test for every allowed transition and rejection of invalid transitions; preserve current statuses when migrating existing rows.

### F1 · P2 · Checkbox answers do not have a consistent contract

**Three cases reproduced.** [SubmissionForm::rules()](../../app/Livewire/SubmissionForm.php#L893) accepts a required checkbox group containing only false values. [API validation](../../app/Http/Controllers/Api/SubmissionController.php#L113) expects a single boolean, so it cannot represent the browser's multi-selection array. [JSON export](../../app/Http/Controllers/SubmissionExportController.php#L369) casts stored selections such as `Alpha, Beta` to `true`, discarding which options were selected.

**Fix / acceptance:** define one canonical selection representation and serialize it consistently. Required groups must contain an allowed selection. Round-trip none/one/multiple selections through browser save/resume, API, JSON, PDF, and XLSX without losing options. Extend the shared contract to conditional required fields, character limits, display-only fields, and actual file uploads; those currently differ between web and API implementations.

### F2 · P2 · Valid API tokens use the anonymous throttle; throttling returns 500

**Reproduced over HTTP and in tests.** Actual middleware ordering runs `ThrottleRequests:api` before [ApiTokenIPMiddleware](../../app/Http/Middleware/ApiTokenIPMiddleware.php). The limiter cannot see the token attribute and uses the anonymous IP bucket. The catch-all exception renderer in [bootstrap/app.php](../../bootstrap/app.php#L133) then turns a throttling exception into HTTP 500, losing Retry-After.

**Fix / acceptance:** order authentication before the token-aware limiter while retaining protection against failed authentication. Preserve HTTP exception status and headers. Verify different tokens behind one IP receive the configured independent allowances and that exceeding a limit returns 429 with retry guidance. Test through actual routes, not just limiter closures.

### F3 · P2 · API deletion leaves uploaded files behind

**Reproduced.** [API form deletion](../../app/Http/Controllers/Api/FormController.php#L210) deletes fields first, cascading away the values needed by `Form::deleting` to identify stored files. A synthetic attachment remained after its form was deleted. API submission deletion similarly deletes values/rows without file cleanup.

**Fix / acceptance:** use one deletion service across API, web, admin, and account deletion paths, with validated file ownership and explicit failure handling. Verify both database and filesystem cleanup, while unrelated files remain untouched. Include scan records and public header images. Audit orphaned files before choosing a retention cleanup policy.

### U1 · P2 · Submission feedback and navigation need repair

**Browser-confirmed:** leaving a required field on step one blank, advancing, and submitting on step two produces the generic summary “This field is required.” The invalid field and its inline error remain hidden; the UI does not identify the step or focus the field. There is a visible summary: the issue is its lack of actionable context, not absence of all error output.

**Browser-confirmed:** guests see “Save as Draft,” but clicking it returns silently because the server only saves authenticated drafts. The mobile cookie notice covers a substantial area of the form; it is dismissible, and the sampled viewport did not horizontally overflow.

**Source-confirmed:** [“Preview Form”](../../resources/views/forms/edit.blade.php#L27) links to `forms.show`, whose controller requires a published/open form, instead of the dedicated `forms.preview` route. Draft and closed-form previews can therefore fail. The thank-you page also offers no submission-specific receipt reference, making anonymous support follow-up harder.

**Fix / acceptance:** identify and navigate to invalid fields; announce feedback; show guest saving limits before data entry; link the real preview; give submitters a useful receipt. Verify keyboard navigation and recovery after validation/network failures on both desktop and mobile. Decide how anonymous drafts and receipts should work before implementing them.

### U2 · P2 · Accessibility issues recur across shared controls

Axe found **29 failing node occurrences** across seven page/viewport samples: 24 contrast, three unnamed links, and two unnamed buttons. These include repeated controls, not 29 unique defects. See [browser-results.json](evidence/browser-results.json).

Specific review locations:

- `resources/views/submissions/create.blade.php:4` — back link lacks an accessible name.
- `resources/views/forms/edit.blade.php:6` — back link lacks an accessible name.
- `resources/views/livewire/form-field-manager.blade.php:125` — section-collapse buttons lack names and expanded-state information.
- `resources/views/livewire/submission-form.blade.php:255` — inline errors are not programmatically associated with inputs; focus does not move to invalid fields.
- `resources/views/livewire/form-field-manager.blade.php:118` and `:189` — drag handles need usable keyboard reordering controls; existing server move methods are not surfaced here.
- Shared `bg-sky-600` / white 14 px controls measured 4.09:1; some muted builder text measured roughly 2.4–2.5:1. Axe expects 4.5:1 for this text.

**Acceptance:** fix shared colors and control semantics, then rerun axe, keyboard-only journeys, focus/modal checks, and a screen-reader review. Also review the shared page title, missing skip link, group legends, reduced motion, and save/error announcements. Source review followed the [Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md); automated checks are not a compliance certification.

### O3 · P2 · Queue and maintenance operations lack dependable checks

**Configuration finding.** Production's worker timeout is 180 seconds, while [database queue retry_after](../../config/queue.php#L43) defaults to 90. With overlapping workers, a long-running job can become eligible for another attempt before the first worker times out. Laravel documents the required relationship in its [queue timeout guidance](https://laravel.com/docs/12.x/queues#job-expirations-and-timeouts).

The only registered schedule is `inspire`. Empty-draft pruning exists as a manual command, but no schedules for retention, failed-job pruning, or stale custom uploads were found. Local temporary upload cleanup is not equivalent to cleaning `private/temp-submissions`. The deployed scanner and notification pipeline remain untested.

**Fix / acceptance:** choose compatible timeout/retry budgets, test worker interruption and duplicate execution, monitor queue age and failed jobs, and verify clean/malicious/unavailable scanner outcomes. Record the operator's retry procedure. Schedule agreed maintenance with a dry-run/recovery process where data deletion is involved.

### PERF1 · P2 · Bulk JSON export issues queries per submission

**Measured.** Exporting one submission ran nine SQL queries; exporting five ran 21. [prepareSubmissionDataForJson()](../../app/Http/Controllers/SubmissionExportController.php#L341) loads form/categories/fields through each submission, so the already chunked export still adds approximately three queries per row in this fixture.

**Fix / acceptance:** reuse the loaded form schema and load relationships per batch. Verify query counts stay approximately constant within a chunk; measure time/memory at an agreed realistic export size. Also paginate the unbounded “My Forms”/dashboard form queries and add indexes based on measured production query plans. The XLSX exporter already uses `lazyById` and explicit string cells, which is worth retaining.

### M1 · P2 · API documentation and operational guidance need consolidation

The generated OpenAPI document has no bearer security scheme despite the custom authentication middleware. Scramble 0.13 reports that `SubmissionValueResource` does not declare its model. README's Vite 5 description differs from the Vite 8 lock; Docker instructions omit the external network/host setup needed by the supplied deployments. Architectural notes refer to removed workflow components. The README appropriately marks custom workflows as work in progress; they should not become an implicit handover commitment.

The privacy document starts by describing `lhc.lu`, contains generic retention language, and does not clearly describe this platform's report/upload/scanner lifecycle. `composer.json` still carries skeleton MIT metadata while the repository license is AGPL-3.0. `public/.well-known/security.txt` expired on 31 December 2025. These are documentation/policy review items, not findings of legal noncompliance.

**Acceptance:** publish accurate setup, architecture, API examples, roles/statuses, production configuration, backup/restore, incident response, and ownership-transfer guidance. Have the responsible organizational owners validate privacy/retention and license statements. Include mail, scanning, deployment, DNS/TLS, and secret ownership without putting credentials in the repository.

### M2 · P2 · CI and abuse controls need tests at their actual boundaries

Existing CI has pinned action revisions, formatting, dependency review, PHP tests, and an asset build. However, it runs SQLite and no browser suite. Several existing tests verify policy methods or limiter definitions without exercising all endpoint paths. Audit probes demonstrate why a green suite is insufficient here. No public submission persistence quota or per-submission idempotency mechanism was found; the temporary upload endpoint's throttle does not limit all submission writes.

**Acceptance:** turn agreed audit invariants into ordinary regression tests; add MySQL migrations/status tests, a small real browser journey, and release health checks. Test revoked access and stale components across entry points. Define a proportionate public-submission rate/quota and duplicate-submit policy. Validate ordinary users can still submit on shared networks. Assign reviewers and ownership of CI/dependency failures; determine branch protection from the repository host rather than assuming it exists.

## Strengths to preserve

- Good feature/security test foundation, clear separation of API endpoints, and a small recognizable Laravel/Blade/Livewire stack.
- Hashed API tokens, explicit abilities, token lifecycle auditing, bounded API pagination, IP restrictions, and failed-authentication throttling in the working tree.
- Private attachment storage, scoped field checks, upload type/size validation, download path checks, and fail-closed scan gating when enabled.
- Asynchronous scanning after commit, retries, scan-failure records, and owner alerts instead of blocking the request on scanning.
- Responsive form rendering, working conditional select visibility, useful field grouping, upload indicators, and explicit destructive-action confirmations.
- Streamed/chunked exports, spreadsheet formula protection, pinned production volume names, and building images before stopping the current deployment.

## Suggested remediation and handover sequence

This is an ordering proposal using 31 October as context, not a fixed effort estimate.

1. **Protect records and close access defects:** S1, S2, D1, D2, S3. Agree the ownership/status/permission matrix before changing behavior.
2. **Establish a reproducible release:** settle and commit the existing working changes; address S4, S5, O1, and D3. Apply dependency/security fixes on a reviewable branch. The Laravel 13 trial provides a feasible candidate.
3. **Restore correct user workflows:** checkbox round trips, API throttling and cleanup, previews, actionable errors, and accessibility. Add the corresponding regression journeys.
4. **Prove operation and recovery:** restore from backup, deploy and roll back on staging, test real mail/scanning/queue failures, and document the result.
5. **Have the successor operate it:** a maintainer unfamiliar with the implementation should set it up, publish a form, process a report, diagnose a failed scan, deploy, and restore using only the documentation.

Avoid using the final handover period for a simultaneous Filament/Livewire major rewrite or unfinished workflow feature expansion. Separate small domain services for authorization, field validation, persistence, and cleanup where that directly removes the duplicated behavior found here.

## Handover acceptance checklist

- [ ] Named product owner, technical maintainer, operations contact, and backup maintainers; remaining findings have owners and accepted deadlines.
- [ ] Role × form visibility × assignment × submission status matrix agreed and enforced across API, Livewire, downloads, and exports.
- [ ] P0/P1 defects resolved or explicitly accepted by the accountable owner with a documented mitigation.
- [ ] Departing account's forms and integration ownership transferred; account/session/token revocation rehearsed without deleting retained reports.
- [ ] Current code, new migrations, configuration examples, and tests committed; a clean clone reproduces the release.
- [ ] MySQL fresh install and upgrade from representative data pass; schema changes preserve historical answers/statuses.
- [ ] Core browser journeys pass for guest, submitter, evaluator, form owner, and administrator, including denied actions and mobile/keyboard paths.
- [ ] Latest reviewed dependencies pass audits; upgrade branch and rollback version are identified.
- [ ] Clean and malicious test files and an unavailable scanner produce the expected download gate and operator alert; mail delivery is demonstrated.
- [ ] Production release has matching assets and healthy app/database/queue/scanner checks; proxy, HTTPS, secure cookies, and trusted hosts/proxies are verified in deployment.
- [ ] Backup and restore have been independently exercised, including attachments, public images, and encryption keys; recovery time and data-loss tolerance are recorded.
- [ ] Monitoring, failure notification destinations, queue retry, retention, incident response, and rollback procedures have named owners.
- [ ] Repository, hosting, DNS, certificates, SMTP, Pandora, CI credentials, service accounts, and dependency alerts no longer depend on one departing employee.
- [ ] Privacy/retention statements, security contact expiry, API docs, known limitations, and licensing metadata are reviewed by the relevant owners.
- [ ] Successor completes a documented operating rehearsal; unresolved production assumptions above are closed with evidence.
