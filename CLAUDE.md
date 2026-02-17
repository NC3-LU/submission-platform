# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

NC3 Submission Platform — a form management system for secure submissions, built by Luxembourg House of Cybersecurity. Features dynamic form building, access controls, file uploads with malware scanning, submission workflows, and a REST API.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+, Livewire 3.6, Filament 3.3 (admin panel)
- **Auth:** Sanctum + Jetstream + Fortify (2FA, email verification)
- **Frontend:** Blade + Livewire components, Tailwind CSS 3.4 (with forms/typography plugins), Vite 5
- **Database:** MySQL 8.0
- **API Docs:** Scramble (auto-generated at `/docs/api`)
- **PDF Export:** DomPDF
- **File Scanning:** Pandora integration (optional, configurable)

## Commands

```bash
# Dev server
npm run dev              # Vite dev server
php artisan serve        # Laravel dev server

# Testing
composer test            # Run all PHPUnit tests
./vendor/bin/phpunit tests/Feature/Api/FormApiTest.php  # Single test file
./vendor/bin/phpunit --testsuite=Feature                # Suite only
composer test:coverage   # HTML coverage report

# Code style
composer lint            # Check style (Pint, dry-run)
composer lint:fix        # Auto-fix style (Pint)

# Docker
docker-compose up        # MySQL + PHP-FPM app

# Custom Artisan commands
php artisan app:create-user        # Create a user
php artisan app:scan-submissions   # Scan existing submission files via Pandora
```

## Architecture

### Routing

- **Web routes** (`routes/web.php`): Public form listing/submission (guarded by `FormAccessMiddleware`), authenticated form CRUD, field management, workflows, exports
- **API v1 routes** (`routes/api.php`): RESTful under `/api/v1/` — tokens, forms, form access-links, form submissions. Protected by `api.token.ip` middleware for IP validation

### Key Directories

- `app/Http/Controllers/Api/` — REST API controllers (separate from web controllers)
- `app/Http/Controllers/` — Web controllers for forms, submissions, workflows, exports
- `app/Livewire/` — Reactive components: `FormFieldManager` (field/category CRUD with drag reordering), `SubmissionForm` (front-end rendering), `SubmissionIndex`, `WorkflowManager`
- `app/Filament/` — Admin panel resources: Users, API Tokens, API Logs
- `app/Services/` — `FileScanService` (Pandora integration), `DashboardStatisticsService`
- `app/Policies/` — `FormPolicy`, `SubmissionPolicy`, `FormAccessLinkPolicy`
- `app/Http/Middleware/` — `FormAccessMiddleware` (visibility gate), `ApiLogMiddleware`, `ScanUploadedFiles`, `ValidateApiTokenIp`
- `app/Http/Resources/` — API JSON resources

### Data Model

- **Form** → has many **FormCategory** (ordered sections) → has many **FormField** (ordered, typed: text/textarea/select/checkbox/radio/file/header/description)
- **Form** ↔ **User** via `form_users` pivot (with `can_edit` flag)
- **Form** → has many **FormAccessLink** (public tokens for sharing)
- **Submission** (UUID primary key via `HasUuids`) → has many **SubmissionValues** (one per field)
- **ApiToken** — SHA-256 hashed, supports IP restrictions, expiration, usage tracking
- **StatusType** — custom workflow statuses for submissions

### Key Patterns

- **Visibility levels:** Forms have public/authenticated/private visibility, enforced by `FormAccessMiddleware`
- **API token auth:** Tokens are SHA-256 hashed in DB; plaintext only returned on creation. IP restrictions stored as comma-separated list
- **File scanning:** Pandora integration is optional (`PANDORA_ENABLED`). `ScanUploadedFiles` middleware intercepts uploads; `FileScanService` handles temp files, retries, UUID filename generation
- **Markdown rendering:** Form descriptions support Markdown via `MarkdownHelper` (auto-loaded in composer.json) and `@tailwindcss/typography`
- **Field ordering:** Categories and fields have an `order` column with move-up/move-down swap logic in Livewire components
- **Export throttling:** PDF/JSON exports use separate rate limiters (`throttle:export`, `throttle:bulk-export`)

### Testing

- Tests use MySQL (or optionally SQLite in-memory — see `phpunit.xml` comments)
- Pandora is disabled in tests by default; mock `FileScanService` when testing file scanning
- Factories available: `UserFactory`, `FormFactory` (with `published()`, `public()` states), `SubmissionFactory` (with `submitted()` state)
- Test structure: `tests/Feature/Api/` for API endpoints, `tests/Feature/` for middleware, `tests/Unit/` for services

### Environment

Key non-standard env vars (see `.env.example`):
- `PANDORA_ENABLED`, `PANDORA_URL`, `PANDORA_TIMEOUT`, `PANDORA_BLOCK_MALICIOUS` — file scanning config
- `API_DOCS_ALLOWED_DOMAINS` — restrict Scramble API docs access
- `CORS_ALLOWED_ORIGINS` — CORS whitelist
