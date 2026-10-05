# Deployment and operations checklist

The repository does not identify the hosting provider, production database endpoint, or deployment mechanism. Apply the provider-specific portions of this checklist with the hosting operator; do not assume that local configuration represents production.

## Before deployment

- Provision PHP 8.2+, required PHP extensions, Composer, and a supported MySQL/MariaDB database.
- Configure the web root to serve only the project's `public/` directory. Keep `.env`, `vendor/`, application source, and private files out of the web root.
- Configure production values outside version control: `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, HTTPS `APP_URL`, database credentials, SMTP settings, and any enabled OAuth credentials.
- Enforce HTTPS before enabling HSTS at the app or hosting edge. Confirm `SESSION_SECURE_COOKIE=true`.
- Make `storage/` and `bootstrap/cache/` writable by the PHP runtime. Keep proposal uploads and signatures on private storage.
- Confirm scheduled tasks, queue workers, and broadcasting are either configured or intentionally disabled for the chosen deployment.

## Database change procedure

1. Test the exact release and all migrations against a staging database using the same database engine/version as production.
2. Before production changes, take a consistent database backup (and a copy of private uploaded files when the release affects storage).
3. Verify the backup file is non-empty and perform a restore drill to a separate database. Do not test restore against production.
4. Deploy code and run `php artisan migrate --force` against the intended production connection. Record the release identifier and migration output.
5. Run smoke checks: `/up`, login, role-specific dashboards, request submission, each approval stage, cancellation, equipment return, private document access, and token expiry.
6. Monitor application logs, web-server errors, failed jobs, database errors, and notification delivery after release.

Example database-only backup/restore commands for MySQL-family servers (adapt host, port, TLS options, and credential handling to the provider; avoid putting passwords directly in shell history):

```sh
mysqldump --single-transaction --routines --triggers --host="$DB_HOST" --user="$DB_USERNAME" "$DB_DATABASE" > pitfr-before-release.sql
mysql --host="$RESTORE_HOST" --user="$RESTORE_USER" "$RESTORE_DATABASE" < pitfr-before-release.sql
```

The backup must be encrypted and access-restricted according to institutional policy. Uploaded private files require a separate protected backup/restore plan; a database dump does not include them.

## Rollback and incident response

- Prefer rolling back application code only when the previous release remains compatible with the current schema. Do not automatically run `migrate:rollback` in production; review each migration's `down()` method and its data-loss implications first.
- For incompatible schema or data changes, use a reviewed forward-fix or restore plan with an explicit maintenance window and approval.
- Keep the previous deployable artifact and release configuration available until smoke checks pass.
- On suspected secret exposure, revoke/rotate affected database, mail, OAuth, and app credentials through the hosting provider, update managed secrets, invalidate active application keys/tokens as appropriate, and review access logs. This repository cannot establish whether local ignored production-looking values are active or exposed.
- Confirm effective production security headers and CORS origins from the deployed endpoint; source code alone cannot verify hosting-edge configuration.

## Local validation

```sh
php artisan test --compact
composer audit --no-interaction
npm audit --audit-level=high
npm run build
php artisan route:list
```

Automated tests run with SQLite and do not substitute for the staging migration, MySQL concurrency, backup-restore, HTTPS/header, or provider-specific deployment checks above.
