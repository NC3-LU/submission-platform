# Project Name

## Overview


The NC3 Submission Platform is a sophisticated form management system designed for secure submission of different forms. It combines flexible access controls and collaborative features.


## Key Features

### Dynamic Form Builder
- No-code form creation interface
- Custom workflow support [WIP]
- File upload capabilities
- Drafting before submitting

### Security & File Scanning
- **Pandora Integration** - Automated malware scanning for uploaded files using [CIRCL's Pandora](https://github.com/pandora-analysis/pandora)
- ClamAV-based antivirus scanning
- Automatic blocking of malicious file uploads (configurable)
- Scan results displayed in submission views
- Admin panel for viewing all scan results
- CLI command to scan existing files: `php artisan app:scan-files --all`

## Requirements

- PHP 8.2+
- Laravel Framework
- API Documentation via Scramble
- Frontend with Livewire and Filament

## Installation

1. Clone the repository:
```bash
git clone [repository-url]
```

2. Install dependencies:
```bash
composer install
```

3. Configure your environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Update your `.env` file with appropriate settings

### Pandora Configuration (Optional)

To enable malware scanning for uploaded files, configure Pandora in your `.env`:

```env
PANDORA_ENABLED=true
PANDORA_URL=http://pandora:6100
PANDORA_TIMEOUT=15
PANDORA_BLOCK_MALICIOUS=true
```

For Docker deployment, use `scripts/setup-pandora.sh` to start the Pandora stack alongside your application.

## API Documentation

API documentation is automatically generated using Scramble and can be accessed at `/docs/api` when running the application. The OpenAPI specification is exported to `api.json`.

## Security

For security-related matters, please contact:
- Security issues: abuse@lhc.lu
- Data protection: privacy@lhc.lu

or via GitHub Security Advisory for critical vulnerabilities
https://github.com/CybersecurityLuxembourg/submission-platform/security/advisories

## License

This project is licensed under the GNU Affero General Public License v3.0. See the [LICENSE](LICENSE) file for details.


## Support

For general inquiries:
- Email: info@lhc.lu
- Address: Luxembourg House of Cybersecurity, 122, Rue Adolphe Fischer, L-1521 Luxembourg


## Roadmap
- Access links for specific emails which need to be confirmed via an unique code which is sent via mail
- Custom Workflow definition after a submission
    - Who shall do what
    - Who shall be notified
    - Shall the user resubmit
    - etc
- API extension
- Encrypted file storage
- Enhanced Pandora features (YARA rules, VirusTotal integration)
