# NC3 Submission Platform

## Overview

The NC3 Submission Platform is a form management system built by the Luxembourg House of Cybersecurity for the secure submission and handling of structured reports. It combines a no-code form builder, flexible access controls, malware scanning of uploads, collaborative review workflows and a REST API.

See the [CHANGELOG](CHANGELOG.md) for release history.

## Key Features

### Dynamic Form Builder
- No-code form creation with ordered sections and typed fields (text, textarea, select, checkbox, radio, file, header, description)
- Conditional field visibility — show or hide a field based on a previous answer
- Per-form header banner image with drag-to-reposition and an accent colour auto-extracted from the image
- Markdown support in form and field descriptions
- Form cloning (deep copy of sections and fields)
- Availability windows (`available_from` / `available_until`) so a form only accepts submissions within a period
- Drafting and autosave before submitting
- Custom workflow support [WIP]

### Access Control
- Per-form visibility: public, authenticated or private
- Shareable access links with revocable tokens
- Per-user form assignment with an edit permission flag

### Security & File Scanning
- **Pandora integration** — uploaded files are scanned asynchronously by [CIRCL's Pandora](https://github.com/pandora-analysis/pandora) (ClamAV-backed)
- Downloads are gated fail-closed until a file passes its scan
- Optional automatic blocking of malicious uploads (`PANDORA_BLOCK_MALICIOUS`)
- Scan results shown in submission views, with an admin panel listing all results
- CLI backfill for existing files: `php artisan app:scan-files --all`
- Strict upload allowlist, path-traversal protection and response-hardening headers
- Two-factor authentication and email verification (Jetstream + Fortify)

### API
- REST API under `/api/v1/` for forms, form access links and submissions
- SHA-256 hashed API tokens with optional IP restrictions, expiry and usage tracking
- Request logging and per-endpoint rate limiting

### Exports
- PDF (DomPDF) and JSON export of submissions, individually or in bulk, with separate rate limits

## Requirements

- PHP 8.2+
- Laravel 12 / Livewire 3 / Filament 3
- MySQL 8.0
- Node.js 20+ (Vite 5, Tailwind CSS 3.4)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/NC3-LU/submission-platform.git
cd submission-platform
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Configure your environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Update your `.env` file with appropriate settings, then:
```bash
php artisan migrate
php artisan storage:link
npm run build
```

5. Create an administrator:
```bash
php artisan app:create-user
```

### Docker

```bash
docker-compose up          # MySQL + PHP-FPM app (development)
```

Production uses `docker-compose.prod.yml`; see `scripts/deploy.sh`.

### Pandora Configuration (Optional)

To enable malware scanning for uploaded files, configure Pandora in your `.env`:

```env
PANDORA_ENABLED=true
PANDORA_URL=http://pandora:6100
PANDORA_TIMEOUT=15
PANDORA_POLL_INTERVAL=2
PANDORA_BLOCK_MALICIOUS=true
```

Scanning runs on the queue, so a worker must be running (`php artisan queue:work`). For Docker deployments, `scripts/setup-pandora.sh` starts the Pandora stack alongside the application.

## Development

```bash
npm run dev              # Vite dev server
php artisan serve        # Laravel dev server

composer test            # Run the test suite
composer test:coverage   # HTML coverage report
composer lint            # Check code style (Pint, dry-run)
composer lint:fix        # Auto-fix code style
```

## API Documentation

API documentation is automatically generated using Scramble and can be accessed at `/docs/api` when running the application. The OpenAPI specification is exported to `api.json`. Access to the docs can be restricted with `API_DOCS_ALLOWED_DOMAINS`.

## Security

For security-related matters, please contact:
- Security issues: abuse@lhc.lu
- Data protection: privacy@lhc.lu

or via GitHub Security Advisory for critical vulnerabilities
https://github.com/NC3-LU/submission-platform/security/advisories

## License

This project is licensed under the GNU Affero General Public License v3.0. See the [LICENSE](LICENSE) file for details.

## Support

For general inquiries:
- Email: info@lhc.lu
- Address: Luxembourg House of Cybersecurity, 122, Rue Adolphe Fischer, L-1521 Luxembourg

## Roadmap
- Access links for specific emails, confirmed via a unique code sent by mail
- Custom workflow definition after a submission
    - Who shall do what
    - Who shall be notified
    - Shall the user resubmit
    - etc
- API extension
- Encrypted file storage
- Enhanced Pandora features (YARA rules, VirusTotal integration)
