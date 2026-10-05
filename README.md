# PITFR Reservation Management System

PITFR is a Laravel application for submitting, reviewing, and approving reservations for school venues and equipment. It uses Blade and Vite for the web interface, Laravel sessions for browser authentication, Sanctum bearer tokens for API authentication, and MySQL or SQLite through Laravel's database layer.

## Requirements

- PHP 8.2 or later with the extensions required by Laravel, including `pdo_sqlite` for tests and the selected PDO driver for deployment
- Composer
- Node.js 20 or later and npm
- MySQL/MariaDB for a hosted multi-user deployment, or SQLite for local development

The backend framework major version is Laravel 12. Exact locked package versions are in `composer.lock` and `package-lock.json`.

## Local setup

1. Install backend packages with `composer install`.
2. Copy `.env.example` to `.env` and generate the application key with `php artisan key:generate`.
3. Configure the database and mail settings in `.env`. The sample file defaults to SQLite; create `database/database.sqlite` if needed.
4. Run `php artisan migrate`.
5. Install frontend packages with `npm ci` and build assets with `npm run build`.
6. Start the local server with `php artisan serve`.

For a combined local development process, `composer run dev` starts the application, queue listener, log viewer, and Vite dev server. Do not use development servers for production hosting.

## Configuration and secrets

`.env.example` lists the supported environment variable names. Configure values through the deployment platform's secret manager or an untracked server-side `.env`; never commit production values.

For production, set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, the canonical HTTPS `APP_URL`, a production database, SMTP credentials, and secure session cookies. Configure Google OAuth only if the feature is enabled. Ensure `SESSION_SECURE_COOKIE=true` when HTTPS is enforced. Keep private documents and signatures outside the public web root.

## Tests and checks

```sh
php artisan test --compact
composer audit --no-interaction
npm audit --audit-level=high
npm run build
```

The test suite uses in-memory SQLite. It does not prove MySQL locking behavior or that a hosted database has every migration applied; validate those separately in staging.

## API overview

All API URLs are prefixed with `/api`. Login is `POST /api/login` and accepts `username` and `password`; successful responses contain a Sanctum token with a 24-hour expiry. Send it as `Authorization: Bearer <token>`. Login is limited to 10 attempts per minute per client IP. `POST /api/logout` revokes the current token.

| Method and path | Access | Purpose |
|---|---|---|
| `GET /api/user` | Authenticated active account | Return current user |
| `GET /api/reservations` | Public | Publicly displayable calendar event data |
| `GET /api/facility-requests` | Authenticated; data scoped by role | Paginated request list |
| `POST /api/facility-requests` | Requestor/admin policy | Submit a reservation |
| `GET /api/facility-requests/{id}` | Owner, admin, or assigned custodian | View an authorized request |
| `PUT/PATCH /api/facility-requests/{id}` | Authorized updater | Currently unsupported; returns `501`. Use the web reschedule workflow. |
| `DELETE /api/facility-requests/{id}` or `POST .../{id}/cancel` | Owner/admin policy; pending only | Cancel a pending request |
| `POST .../{id}/approve` or `/reject` | Assigned custodian and matching resource stage | Endorse or reject a resource stage |
| `POST .../{id}/return-equipment` | Assigned equipment custodian | Record itemized returns |
| `GET /api/equipment/availability`, `/api/venue/availability` | Authenticated active account | Check availability |

Authorization is additionally enforced by policies and resource assignment checks; see [the API and role notes](docs/API.md) for action details.

## Deployment and operations

The deployment host/provider is not defined in this repository. Point the web server document root at Laravel's `public/` directory; keep `.env`, source, and private `storage/` files outside the public document root. Grant the PHP runtime write access only to `storage/` and `bootstrap/cache/`.

Use the backup, migration, smoke-test, and rollback checklist in [docs/OPERATIONS.md](docs/OPERATIONS.md). Take and verify a database backup before production migrations. Do not apply the new reservation-resource-lock migration using manually guessed SQL; deploy its migration file and run `php artisan migrate --force` on the configured database.
