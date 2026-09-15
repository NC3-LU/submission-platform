# Getting started

This guide creates a **local development installation** with PHP and MySQL on your machine. If you only need to fill in or manage forms on an existing service, start with the [project overview](https://github.com/NC3-LU/submission-platform/wiki/Home).

For production, use [Running the platform](https://github.com/NC3-LU/submission-platform/wiki/Running-the-Platform) and the operations runbook.

## What you need

| Tool | Project requirement |
| --- | --- |
| PHP | 8.3 or later, with the extensions required by Composer |
| Composer | Version 2 |
| MySQL | 8.0 |
| Node.js | 24 for the build used by this project; `package.json` also accepts 26 |
| npm and Git | For dependencies and source code |

The PHP environment needs extensions including cURL, DOM/XML, fileinfo, GD, intl, mbstring, PDO MySQL, and ZIP. Add PDO SQLite for the default test suite. Composer reports missing platform requirements during installation.

## 1. Download the project and dependencies

```bash
git clone https://github.com/NC3-LU/submission-platform.git
cd submission-platform
composer install
npm ci
cp .env.example .env
```

These instructions assume a new checkout. Keep any existing `.env` when working on an installation that has already been configured. The committed lockfiles select dependency versions; use `npm ci` for a reproducible install.

## 2. Configure your local database

Create an empty MySQL database named `submission_platform` and a local database account with permission to manage its tables. Update the matching entries in `.env`:

```dotenv
APP_NAME="NC3 Submission Platform"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=submission_platform
DB_USERNAME=submission_app
DB_PASSWORD=REPLACE_WITH_YOUR_LOCAL_DATABASE_PASSWORD

MAIL_MAILER=log
QUEUE_CONNECTION=database
INTEGRATION_QUEUE_CONNECTION=database
PANDORA_ENABLED=false
WEBHOOKS_ENABLED=false
```

Replace the database credentials with the ones you created. Keep `.env` out of version control. `MAIL_MAILER=log` writes development mail to the application log instead of sending it.

## 3. Initialize the new installation

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
composer check-platform-reqs
```

Generate the application key once for this new installation. Keep that key when upgrading an existing service: encrypted application data depends on it.

The storage link exposes public assets such as form banners. Submission attachments stay on the separate private disk.

## 4. Start the application and background processes

Run each command in a separate terminal, from the project directory:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

```bash
php artisan queue:work --tries=3 --sleep=3 --timeout=180 --max-time=3600
```

```bash
php artisan schedule:work
```

Open **http://127.0.0.1:8000**. The queue worker handles background tasks such as exports and scans; the scheduler runs cleanup and recovery tasks. The worker above exits after an hour, so restart it when needed during development. Production uses a service manager or restarting containers.

For automatic frontend updates while editing, also run `npm run dev`.

## 5. Create an account

For a form-building account, run:

```bash
php artisan user:create --role=internal_evaluator
```

The command prompts for the name, email, and password. To create an administrator instead, use `--role=admin`. The command is **`user:create`**; there is no `app:create-user` command in the current code.

Sign in and complete email verification. With the local log mailer, the verification message is in `storage/logs/laravel.log`; open its verification URL locally. Do not publish the log or verification link.

The `/admin` panel has an additional application rule: the account must have the `admin` role, a verified email address, and an address ending in `@nc3.lu`. An administrator at another domain can have application permissions without being allowed into this panel. See `User::canAccessPanel()` when adapting the project for another organization.

## 6. Check that the installation works

```bash
php artisan app:health
```

Create a disposable form, preview it, publish it, submit a sample response, and confirm you can read it. This checks the user journey as well as basic service readiness.

For API documentation, open `/docs/api`. Its access policy uses `API_DOCS_ALLOWED_DOMAINS` and any saved API settings. `API_DOCS_PUBLIC=true` relaxes the email-domain restriction for authenticated users in a test environment.

## Prefer Docker?

The repository includes two deployment layouts. `docker-compose.yml` expects an existing `dokploy-network` and routing through that network; it does not publish an application port on localhost. Running `docker compose up` alone is therefore not a complete browser setup on a fresh machine.

Read [Running the platform](https://github.com/NC3-LU/submission-platform/wiki/Running-the-Platform) before choosing or starting a Docker layout.

Next: [Managing forms](https://github.com/NC3-LU/submission-platform/wiki/Managing-Forms) · [Development](https://github.com/NC3-LU/submission-platform/wiki/Development)
