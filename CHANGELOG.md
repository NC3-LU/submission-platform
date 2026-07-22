# Changelog

All notable changes to the NC3 Submission Platform are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [2.1.0] - 2026-07-22

### Added
- **Form header images** — form owners can add a banner image to any form (Google-Forms style): upload, drag to reposition, with an accent colour auto-extracted from the image and manually overridable. Banners render on the fill-out page, the preview, and the form cards.
- Footer quick links to cybersecurity.lu, nc3.lu, circl.lu and lhc.lu.
- REST API now exposes each form's `header_image_url` and `header_theme_color`.

### Changed
- New Cybersecurity Luxembourg logo in the top navigation and on the login/authentication pages.
- Footer rebranded to "Cybersecurity Luxembourg — National Cybersecurity Competence Center"; homepage tagline updated to reference the Cybersecurity Luxembourg Ecosystem.
- Redesigned form cards on the homepage and forms list — full-width header banners, hover elevation, and the whole card is clickable to open a form.

### Fixed
- "Add Section" and "Add Field" now open as centered dialogs, consistent with the other modals (previously right-side slide-outs that overlapped the top navigation).
- Hardened image-upload validation (type, size and dimension limits; hashed file storage).
- Uploaded images now always load after a deploy (the `public/storage` symlink is recreated automatically on container start).

## [2.0.0] - 2026-07-20

### Added
- **Malware scanning for uploads** — every uploaded file is scanned asynchronously (Pandora + ClamAV); downloads are gated fail-closed until a file passes the scan.
- **Conditional field visibility** — show or hide fields based on a previous answer (input branching).
- **Form availability windows** — `available_from` / `available_until` dates so a form only accepts submissions within a period.
- **Form cloning** — duplicate a form with a deep copy of its sections and fields.
- Custom error pages matching the platform design language.
- Draft autosave improvements plus an `--untouched-only` prune command for abandoned drafts.

### Changed
- Design refresh: forms, submissions, workflow management and static pages modernized to the sky/slate design language.
- Production-ready deployment: dedicated php-fpm production compose, background queue worker, `.env` mounting and safer migration/deploy handling.

### Security
- Fixed a stored XSS in conditional-field values (JS-context escaping).
- Enforced a strict upload allowlist and stopped logging submission contents.
- Blocked path-traversal attempts on file downloads.
- Removed server-fingerprinting headers (`X-Powered-By`, `Server`) and added response-hardening headers.

### Fixed
- Guest submissions: made `submissions.user_id` nullable and added an `ip_address` column.
- Resolved a 500 error when exporting your own submission as JSON.
- Rate limiters now fall back to sensible defaults when API settings are empty.
- Uploaded files are cleaned up when a form is deleted.

## [1.2.1] - 2025-11-05

### Fixed
- Resolved rate limiter and redirect errors.
- Deployment: disabled strict host-key checking.

## [1.2.0] - 2025-11-05

### Added
- Database-driven API Security Settings management page.
- Character-limit display on form fields.

### Security
- Hardened Assign-Users validation against ID tampering; corrected role checks.

### Fixed
- Field-edit persistence and scoped category validation.
- Ensured `form_id` / `user_id` are set before saving to avoid null `form_id` inserts.
- Made the Pandora integration optional by default with a timeout and robust fallback.

## [1.1.0] - 2025-03-20

### Added
- Dashboard with statistics tied to forms.
- Responsive footer with copyright and legal links.
- Custom workflow support (migrations).

### Changed
- Upgraded the Laravel framework from 11.x to 12.x with dependency updates.
- Redirect users to the forms page after login instead of the dashboard.

### Fixed
- Checkbox persistence/display and Markdown formatting in forms.
- Redirection after submission deletion and form preview authorization.

## [1.0.0] - 2025-01-30

- Initial release.

[2.1.0]: https://github.com/NC3-LU/submission-platform/releases/tag/v2.1.0
[2.0.0]: https://github.com/NC3-LU/submission-platform/releases/tag/v2.0.0
[1.2.1]: https://github.com/NC3-LU/submission-platform/releases/tag/v1.2.1
[1.2.0]: https://github.com/NC3-LU/submission-platform/releases/tag/v1.2.0
[1.1.0]: https://github.com/NC3-LU/submission-platform/releases/tag/v1.1.0
[1.0.0]: https://github.com/NC3-LU/submission-platform/releases/tag/v1.0.0
