# Laravel 13 upgrade assessment

Assessed **14 September 2026**, against the current working tree including its uncommitted changes.

**Result: technically feasible, with a successful isolated installation and regression trial.** The original project's dependencies and application code remain unchanged. The trial is evidence for an upgrade branch, not a deployed release or a fix for the [application defects](2026-09-14-project-assessment.md).

## Verified target and support

The latest stable version available during the assessment was **Laravel 13.31.0**, confirmed by Composer metadata and the [official release](https://github.com/laravel/framework/releases/tag/v13.31.0).

Laravel 13 requires PHP 8.3 or newer. This machine, the Dockerfile, and CI already use PHP 8.3; the application's Composer requirement and README still permit PHP 8.2 and should be updated. Laravel 12's bug-fix window has ended, but security support continues until **24 February 2027**. Laravel 13's published security support runs until **17 March 2028**. These dates support planning the upgrade without treating the major-version number as an immediate emergency. [Laravel support policy](https://laravel.com/docs/13.x/releases)

## Dependency resolution

Changing only `laravel/framework` to `^13.0` is insufficient:

1. The exact Livewire 3.6.4 pin is affected by a current security advisory and blocked by Composer's resolver.
2. After lifting that pin, Scramble 0.12 blocks resolution because it only accepts Illuminate through version 12.

The following candidate resolved and installed successfully with security blocking left enabled:

| Dependency | Existing lock / requirement | Trial result / candidate requirement |
| --- | --- | --- |
| PHP | Requirement `^8.2`; runtime 8.3.6 | Requirement `^8.3`; runtime unchanged |
| Laravel framework | 12.64.0 / `^12.0` | **13.31.0** / `^13.0` |
| Livewire | 3.6.4 / exact `3.6.4` | **3.8.8** / `^3.8.8` |
| Scramble | 0.12.36 / `^0.12.16` | **0.13.43** / `^0.13.43` |
| Filament | 3.3.54 / `^3.3.4` | **3.3.55**, same major requirement |
| Jetstream | 5.5.3 | 5.5.3, already compatible |
| Sanctum | 4.3.3 | 4.3.3, already compatible |
| Laravel DomPDF | 3.1.2 | 3.1.2, already compatible |
| Tinker | 2.11.1 / `^2.9` | **3.0.2** / `^3.0` |
| PHPUnit | 11.5.56 / `^11.0` | **12.5.35** / `^12.0` |

Tinker/PHPUnit changes follow the [official upgrade guide](https://raw.githubusercontent.com/laravel/docs/13.x/upgrade.md). Current Filament 3 and Livewire 3 releases declare Laravel 13 compatibility, and that combination actually installed and passed the trial. Upgrading to Filament 5 or Livewire 4 is not required for this Laravel upgrade.

The unrestricted trial update changed **98 package versions**, including transitive dependencies and development tools. A production upgrade should review that lockfile diff and use a narrower update set where practical. The [candidate manifest](evidence/laravel13-candidate-composer.json), [package changes](evidence/laravel13-package-changes.json), and [registry metadata](evidence/upgrade-package-metadata.json) are retained for review; they are not applied to the project.

## Trial results

The disposable copy used the same application code, PHP 8.3.6, a separate vendor directory, synthetic data, and SQLite. Composer install scripts were initially disabled; package discovery and Filament asset publication were then run explicitly.

| Check | Laravel 13 result |
| --- | --- |
| Dependency install | Successful; resolved Laravel 13.31.0 without ignoring advisories or platform constraints. |
| PHP audit | No security advisories or abandoned packages reported in the candidate lock. |
| Existing test suite | **255 tests, 601 assertions, four skips, zero failures**, 9.680 s locally. |
| Pint | Passed for the copied application after excluding/removing audit-only scaffolding. |
| Package discovery | Passed. |
| Route and Blade caches | Built successfully. |
| Filament assets | Published successfully in the disposable copy. |
| OpenAPI export | Succeeds with one warning: `SubmissionValueResource` model cannot be inferred. Add the appropriate resource model PHPDoc and inspect the generated schema. |
| Chromium browser sample | Homepage, login, public form desktop/mobile, dashboard, builder, and admin returned 200; login and conditional visibility worked; no page JavaScript errors. |
| Audit probes | The same 13 of 14 intended invariants fail: upgrading dependencies alone does not repair the discovered application defects. |

The browser sample retained the same accessibility and form-feedback findings as Laravel 12. An entire successful submission journey, live attachment scanning, live mail, MySQL migrations/status behavior, production traffic, and deployment/rollback were not established by this trial. The existing tests include more functional checks than the browser sample, but they do not close those operational gaps.

## Project-specific upgrade work

- **Request forgery protection:** review the Laravel 13 middleware changes with Jetstream, Livewire uploads/actions, and the real HTTPS proxy. The local HTTP browser sample is insufficient to certify production origin/CSRF behavior.
- **Sessions and caches:** explicitly choose serialization and preserve cookie/cache names as appropriate. Enabling JSON session serialization can require users to log in again; private-form access stored in sessions and open drafts need a planned transition. Current dashboard caches store arrays, which simplifies reviewing the cache changes.
- **Database compatibility:** fix the application's API/database status mismatch and test on MySQL before release. Review data-preserving migrations and all previously deployed pending migrations.
- **Generated assets and docs:** publish Filament assets matching the installed version, rebuild application assets, address the Scramble model warning, and describe custom bearer authentication in OpenAPI. Resolve the host `public/` bind-mount problem from O1 so the upgrade's assets actually reach production.
- **Worker deployment:** restart queue workers after deployment and align worker timeout with queue retry timing. Verify serializing and retrying a scan job against the actual database queue and Pandora service.
- **Dependency ownership:** a cleared audit is time-specific. Keep the scheduled dependency checks and assign someone to act on them throughout maintenance.

The official guide also lists changes to MySQL upserts, custom contracts, manager extensions, queue events, and helper functions. No obvious application use of the listed high-risk custom extension patterns was found in the reviewed source; this is a source-review observation, not blanket compatibility proof. Apply the guide against the final upgrade diff rather than copying a new application skeleton over existing configuration.

## Recommended delivery sequence

1. Capture and review the current uncommitted work, establish regression tests for the urgent application findings, and verify backups/ownership transfer.
2. Create a dedicated Laravel 13 branch using the candidate requirements. Review dependencies and generated assets; update PHP/runtime documentation. Keep application fixes identifiable in separate commits.
3. Run the full suite and the new audit regression tests, MySQL tests, API contract checks, and complete browser journeys for each role. Recheck dependency audits and frontend build on the actual CI runtime.
4. Deploy to staging with production-equivalent proxy, queue, mail, and scanner configuration. Exercise clean/malicious/unavailable scanning, token rotation/revocation, closed forms, and preserved draft sessions.
5. Rehearse rollback and have an operator perform a deployment from the documented procedure before selecting a production release date.

**Recommendation:** include Laravel 13 in the remediation release, while prioritizing the confirmed authorization, file ownership, and destructive-deletion defects. The trial removes the main package-compatibility uncertainty; it does not justify skipping staging and restoration checks.
