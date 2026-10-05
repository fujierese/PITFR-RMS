# PITFR Repository Audit

**Audit date:** 2026-10-03  
**Scope:** The audit baseline was a read-only review of application source, routes, configuration, migrations, tests, UI templates/assets, dependency manifests/lockfiles, and CI. Subsequent sections record authorized remediation and current verification. A local ignored `.env.production` file was present; its values are deliberately not reproduced. The active status or external exposure of those values cannot be verified from this checkout.
**Latest validation:** `php artisan test --compact` — 353 passed, 1,487 assertions; `php artisan view:cache`; `npm run build`; `composer audit --no-interaction`; `npm audit --audit-level=high`; focused workflow/security/calendar/notification/time-normalization tests; `git diff --check`. Live MySQL schema/locking, production HTTPS headers, backup/restore, and secret rotation remain operator/staging checks.

## Executive summary

**Current health: 7/10 — application-level Critical/High findings from the baseline are remediated, but deployment readiness is conditional.** Booking transitions now use shared resource locks, inventory returns are itemized, calendar/API payloads are narrowed, password/token controls are stronger, and the main documented defects have regression coverage. The latest full test run is green. Before deployment, an operator still needs to assess/rotate potentially exposed ignored environment credentials, apply and verify migrations on staging MySQL, exercise concurrent approval against that engine, and verify actual HTTPS/headers plus backup restoration. Dashboard scale and controller size remain improvement areas.

No Critical-severity issue was confirmed. Baseline High findings SEC-01, SEC-02, BIZ-01, BIZ-02, and BIZ-03 have been remediated. Current Composer and full npm dependency audits report no known advisories. CI now makes both audits blocking, lints PHP syntax, and smoke-tests SQLite migrations. This source audit cannot verify hosting configuration or MySQL concurrency behavior.

| Category | Health (1-10) | Summary |
|---|---:|---|
| Bugs & errors | 8 | API update now reports `501` rather than false success; request ranges and nested quantities are validated; upload failures are logged. |
| Business logic | 7 | Approval locks, inventory returns, urgent priority, cancellation, date ranges, and calendar conflict checks are aligned; MySQL locking still needs staging verification. |
| Security | 7 | Uploads, inactive accounts, calendar/API list scope, login throttling, token expiry, and baseline response headers are hardened; local secret state and deployed headers need operator verification. |
| API endpoints & routes | 8 | 156 baseline routes; role-scoped API lists, validation, explicit unsupported update response, and cancellation inventory handling are covered. Legacy aliases remain. |
| Notifications | 8 | Equipment endorsements notify requestors; notification cards identify requestor/activity and show a visible read failure. |
| UI/UX & accessibility | 8 | Mobile notification link, menu ARIA, timestamp contrast, and notification failure feedback are corrected; broader visual/manual accessibility checks remain. |
| Printing & documents | 8 | E-signature is confined to print view, print-only CSS is scoped, and the approval details use the direct print action. Browser/paper output still needs manual verification. |
| Database | 6 | Relational tables and common indexes exist; production migration state, MySQL row-lock behavior, and backup restoration remain unverified. |
| Code quality | 5 | Key controllers are very large and route/validation/business logic is duplicated across web and API implementations. |
| CI/CD & DevOps | 7 | CI now blocks on Composer/npm audits, lints PHP syntax, tests, builds, checks route caching, and smoke-tests SQLite migrations; deploy/rollback/monitoring remain provider-specific. |
| Performance | 5 | Availability queries now select approved overlapping reservations instead of scanning all history; primary requestor/Supply Office dashboard queues remain unpaginated. |
| Documentation | 8 | README, API access notes, deployment/backup/rollback checklist, and this audit are present; hosting-specific setup still requires confirmation. |

## Step 0 — Discovery

### Stack and structure

- **Backend:** PHP requirement `^8.2` (local CLI `8.2.12`), Laravel Framework `^12.0` (locked `12.69.1`), Sanctum `^4.3` for API personal access tokens, Socialite `^5.30` for Google OAuth, and Reverb `^1.0` for websocket broadcasting.
- **Frontend:** Blade templates, Node local runtime `24.12.0` (CI uses Node 20), Vite `^7.0.7` (locked `7.3.6`), Tailwind CSS `^4.3.3`, `@tailwindcss/postcss`, Axios, FullCalendar, Laravel Echo, and Pusher JS.
- **Database:** Laravel supports SQLite, MySQL, MariaDB, PostgreSQL, and SQL Server in `config/database.php`. Tests are explicitly configured to use in-memory SQLite. The active environment selected MySQL when `migrate:status` was attempted, but its configured DNS name was unavailable; the live schema/migration state therefore could not be verified. Do not infer production database contents from the local code.
- **Authentication:** Laravel web sessions with login/registration/password reset and Google OAuth; Sanctum bearer personal-access tokens for API routes. Passwords and OTPs use Laravel hashing APIs; password creation/reset/change requires at least 12 characters. Web login has account/IP throttles, API login has an IP throttle, and API bearer tokens expire after 24 hours. Role gates protect dashboard groups; shared request-action routes rely on controller/policy checks. `EnsureActiveAccount` is wired to authenticated web/API surfaces, with deactivation revocation.
- **Hosting/deployment evidence:** `.htaccess` is present, consistent with Apache rewrite support. `.github/workflows/ci.yml` runs Ubuntu/PHP 8.2/Node 20, full dependency audits, PHP syntax lint, SQLite migration smoke test, tests, frontend build, and route-cache verification. No checked-in Dockerfile, compose file, or release workflow was found. Provider-neutral deployment and rollback guidance is in `docs/OPERATIONS.md`; the actual hosting provider and runtime configuration cannot be verified from this repository.
- **Root folders:** `app/` (controllers, models, policies, services, events, notifications), `bootstrap/`, `config/`, `database/` (migrations, factories, seeders), `public/`, `resources/` (Blade, JS, CSS), `routes/`, `scripts/`, `storage/`, `tests/`, `.github/workflows/`, plus `vendor/` and `node_modules/` in the working tree.

### Declared dependencies

Versions below are manifest constraints unless explicitly called an installed version.

| Ecosystem | Direct dependencies |
|---|---|
| Composer runtime | `php ^8.2`; `laravel/framework ^12.0`; `laravel/reverb ^1.0`; `laravel/sanctum ^4.3`; `laravel/socialite ^5.30`; `laravel/tinker ^2.10.1`; `pusher/pusher-php-server *`. Installed direct versions include Laravel `12.69.1`, Reverb `1.8.1`, Sanctum `4.3.1`, Socialite `5.30.0`, Tinker `2.11.1`, and Pusher PHP `7.2.7`. |
| Composer development | `fakerphp/faker ^1.23`; `laravel/pail ^1.2.2`; `laravel/pint ^1.24`; `laravel/sail ^1.41`; `mockery/mockery ^1.6`; `nunomaduro/collision ^8.6`; `phpunit/phpunit ^11.5.50`. |
| npm runtime | `@fullcalendar/core ^6.1.20`; `@fullcalendar/daygrid ^6.1.20`; `@fullcalendar/interaction ^6.1.20`; `@fullcalendar/timegrid ^6.1.20`; `laravel-echo ^2.3.3`; `pusher-js ^8.5.0`. |
| npm development | `@tailwindcss/postcss ^4.3.3`; `autoprefixer ^10.4.27`; `axios ^1.11.0`; `concurrently ^9.0.1`; `laravel-vite-plugin ^2.0.0`; `postcss ^8.5.8`; `tailwindcss ^4.3.3`; `vite ^7.0.7`. |

The lockfiles contain 132 Composer package records and 183 npm package records (the npm count includes the root project entry). Composer runtime and development direct dependencies and npm runtime and development direct dependencies are listed above; the lockfiles provide the complete transitive version inventory. The Composer manifest uses the unconstrained version `"*"` for `pusher/pusher-php-server`; the lockfile currently pins it, but the manifest should declare a compatible range.

`composer outdated --direct` reports updates available for Laravel Framework `12.69.1 → 12.69.3`, Reverb `1.8.1 → 1.12.0`, Sanctum `4.3.1 → 4.3.3`, Socialite `5.30.0 → 5.31.0`, Pail `1.2.6 → 1.2.7`, Pint `1.29.0 → 1.30.4`, Sail `1.54.0 → 1.68.0`, Tinker `2.11.1 → 3.0.2` (major), Mockery `1.6.12 → 1.6.15`, Collision `8.9.1 → 8.9.5`, PHPUnit `11.5.55 → 11.5.56`, and Pusher PHP `7.2.7 → 7.3.0`.

`npm outdated --long` reports available updates: FullCalendar core `6.1.20 → 6.1.21` (latest major 7.1.0), the other FullCalendar modules `6.1.20 → 6.1.21`, Autoprefixer `10.4.27 → 10.6.1`, Concurrently `9.2.4 → 10.0.5` (major), Laravel Echo `2.3.3 → 2.5.0`, Laravel Vite Plugin `2.1.0 → 3.2.0` (major), Pusher JS `8.5.0 → 8.6.0`, and Vite `7.3.6 → 8.3.2` (major). Upgrade major versions in a compatibility change, not as blind lockfile refreshes.

### Dependency audit results

- `composer audit --no-interaction`: **no advisories found**.
- `npm audit --audit-level=low` (all dependencies): **zero vulnerabilities found**; `npm audit --omit=dev` also returned zero.
- The current manifests are not entirely up to date; see direct update availability above. CI audits npm production dependencies only and sets `continue-on-error: true`, so any future npm audit failure is not a build blocker.

### Environment and secret handling

`.env.production` exists in this checkout and contains credential-shaped values for application, database, SMTP, and Google OAuth configuration. It is ignored by Git (`.gitignore:5`) and was not listed by `git ls-files`; its presence does **not** establish that it was committed or deployed. Do not copy its values into this report, source control, tickets, or logs. If the values are or were active outside this machine, rotate them at their respective providers and use deployment-managed secrets. `.env.example` contains placeholders and no secret values.

### Security findings summary

The table below preserves the confirmed **audit-baseline** security findings; remediation status for the current checkout is listed in the current disposition section.

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| 1 | 🟠 HIGH | `app/Http/Controllers/RequestorController.php` | 1171-1177, 1472-1492 | Unvalidated legacy proposal upload is served inline, enabling stored script execution in the app origin. | 9/10 |
| 2 | 🟠 HIGH | `app/Http/Controllers/CalendarController.php` | 351-389 | Admin calendar approval can set final and custodian approval states without required endorsements. | 9/10 |
| 3 | 🟠 HIGH | `app/Services/AvailabilityService.php`; `app/Http/Controllers/RequestActionController.php` | 80-120; 182-184, 359-364 | Baseline: non-atomic availability checks could conflict; shared deterministic resource locks now serialize application workflows. MySQL verification remains necessary. | 9/10 |
| 4 | 🟠 HIGH | `app/Services/AvailabilityService.php`; `app/Http/Controllers/Api/FacilityRequestApiController.php` | 54-73, 127-146; 424-485 | Baseline: return quantities/status-derived inventory could restore unavailable stock. Itemized accounting, usable-unit restoration, and inventory reconciliation are implemented and tested. | 8/10 |
| 5 | 🟠 HIGH | `app/Http/Controllers/AdminController.php`; `app/Http/Middleware/RoleMiddleware.php`; `app/Http/Controllers/AuthController.php`; `bootstrap/app.php` | 447-470; 10-39; 98-125; 13-20 | Baseline: inactive-account enforcement was incomplete. Web/API checks, OAuth rejection, token revocation, and database-session revocation are now wired and tested. | 9/10 |
| 6 | 🟡 MEDIUM | `.env.production`; `.gitignore` | 2-4, 23-28, 51-57, 69-71; 5 | Ignored local deployment file contains production credential-shaped values; active status or external disclosure cannot be verified. | 9/10 |
| 7 | 🟡 MEDIUM | `app/Http/Controllers/CalendarController.php` | 28-35, 113-130 | Authenticated requestors receive all non-cancelled calendar requests and extra requester/emergency details. | 9/10 |
| 8 | 🟡 MEDIUM | `app/Http/Controllers/Api/FacilityRequestApiController.php` | 34-54 | Equipment-custodian request list includes unrelated requests and exposes requester/history fields. | 9/10 |
| 9 | 🟡 MEDIUM | `routes/web.php`; `routes/api.php`; `app/Http/Requests/LoginRequest.php` | 46; 25; 7-25 | Web and API login routes have no visible route-level throttle; the login FormRequest also has no rate-limit logic. OTP resend alone is throttled. | 8/10 |
| 10 | ⚪ LOW | `bootstrap/app.php`; `config/cors.php`; `.htaccess` | 13-20; 1-32; 1-3 | No application-configured CSP, frame, nosniff, or HSTS headers were found. The CORS origin is restricted to `APP_URL`; host-level headers cannot be verified from source. | 7/10 |
| 11 | 🟡 MEDIUM | `config/sanctum.php`; `routes/api.php` | 50; 25-35 | Sanctum token expiration is `null` and API login creates bearer tokens without an explicit per-token expiry; a stolen token can remain valid until revoked. | 9/10 |
| 12 | ⚪ LOW | `app/Http/Controllers/AuthController.php` | 154-159, 178-180 | Password reset and registration accept a minimum length of six characters. Passwords are hashed, but this low minimum weakens resistance to guessing when users choose common passwords. | 8/10 |

## Findings

Severity indicates impact in the observed application context. Paths and lines refer to the checked-in source/lockfiles.

| ID | Category | Severity | File:Line | Description | Recommended Fix | Effort |
|---|---|---|---|---|---|---|
| SEC-01 | Security / uploads | High (baseline; remediated in follow-up) | `app/Http/Controllers/RequestorController.php:1171-1177,1472-1492` at audit baseline | At baseline, legacy `proposal_file` had no file/type/size validation, preserved a client-provided extension, and was served inline. An authorized viewer could execute uploaded active HTML in the PITFR origin. | Follow-up validates the upload, stores it privately with a generated name, and limits inline preview to PDF/JPEG/PNG with `nosniff`; see remediation status below. | S |
| SEC-02 | Security / authentication | High (baseline; remediated in follow-up) | `app/Http/Controllers/AdminController.php:447-470`; `app/Http/Middleware/RoleMiddleware.php:10-39`; `app/Http/Controllers/AuthController.php:98-125`; `bootstrap/app.php:13-20` at audit baseline | At baseline, deactivation did not revoke sessions/tokens, role middleware did not reject inactive users, and Google callback could authenticate/link inactive accounts. `EnsureActiveAccount` existed but was not registered. | Follow-up wires account-state enforcement into web and Sanctum routes, rejects inactive OAuth accounts, and revokes tokens and database-backed sessions on deactivation; see status below. | M |
| BIZ-01 | Business logic / workflow | High (baseline; remediated in follow-up) | `app/Http/Controllers/CalendarController.php:351-389` at audit baseline | At baseline, `approveRequest()` allowed an admin request with no approval `type` to set overall status approved and set venue/equipment statuses approved, bypassing both custodian stages and the guarded Supply Office transition. | Calendar admin final approval now delegates to the Supply Office final-approval workflow, which requires both custodian statuses approved and performs the canonical conflict, history, signature, and notification steps. | S |
| BIZ-02 | Business logic / booking integrity | High (baseline; remediated in follow-up) | `app/Services/AvailabilityService.php:16-57`; `database/migrations/2026_10_03_000001_create_reservation_resource_locks_table.php`; `app/Http/Controllers/RequestActionController.php:182-188,365-371`; `app/Http/Controllers/SupplyOfficeController.php:716-730`; `app/Http/Controllers/Api/FacilityRequestApiController.php:170-178,303-310`; `app/Http/Controllers/RequestorController.php:1290-1325` | At baseline, checks and approval writes did not serialize on shared resources, and web/API submissions checked availability before inserting. Two concurrent approvals of overlapping venue or equipment could both pass the check. | Follow-up uses a dedicated lock row per normalized venue/equipment name, locks all participating rows deterministically, then locks the request, rechecks availability, and writes within the same transaction for approval and submission paths. The priority-override path acquires locks for both requests’ resources. | M |
| BIZ-03 | Business logic / inventory | High | `app/Services/AvailabilityService.php:54-73,127-146`; `app/Http/Controllers/Api/FacilityRequestApiController.php:461-485` | Baseline: return status could make damaged, missing, or partially returned stock appear available. Itemized cumulative return data now validates allocation limits, restores only newly returned usable units, and drives availability reconciliation. | Treat `quantity_available` as authoritative physical free stock, validate returned quantities per equipment item, and restore only verified returned usable units. Reconcile status-derived reservations with inventory updates. | M |
| SEC-03 | Security / information disclosure | Medium | `app/Http/Controllers/CalendarController.php:28-35,113-130`; `routes/web.php:199` | Requestors now receive full calendar data only for their own requests; other-user/public events use a reduced DTO with no requester/organization details, participant count, emergency justification, workflow states, or detail URL. | Return a public-calendar DTO containing only fields and statuses approved for public display, or scope requestor data to the authenticated owner. | S |
| SEC-04 | Security / authorization | Medium | `app/Http/Controllers/Api/FacilityRequestApiController.php:34-54`; `routes/api.php:56-64` | Custodian API lists are scoped to assigned resources and serialized with whitelisted fields/quantities; detail responses omit requester/history information. Unsupported authenticated roles receive `403`. | Scope every branch to resources assigned to that custodian and serialize only authorized fields. | S |
| SEC-05 | Security / secret handling | Medium | `.env.production:2-4,23-28,51-57,69-71`; `.gitignore:5` | A local ignored production environment file contains credential-shaped app, database, mail, and OAuth values. It is not tracked by Git, and the audit cannot establish whether these values are active or were shared/deployed elsewhere. | Rotate any potentially exposed active values; store secrets only in deployment-managed secret storage and keep local production env files out of releases and source control. | S |
| SEC-06 | Security / brute-force protection | Medium | `routes/web.php:46`; `routes/api.php:25`; `app/Http/Requests/LoginRequest.php:7-25` | Baseline: web/API login had no visible throttle. Web login now limits by account and IP; API login has a route throttle. Monitor and tune against real hosting traffic. | Apply per-account and per-IP throttling with progressive backoff/monitoring to web and API authentication endpoints; keep responses generic. | S |
| SEC-07 | Security / response headers | Low | `bootstrap/app.php:13-20`; `config/cors.php:1-32`; `.htaccess:1-3` | Web/API responses now set `nosniff`, same-origin frame protection, referrer policy, and permissions policy; HSTS is set only on secure requests. Same-origin framing is retained for proposal previews. CSP and actual hosting-edge headers remain unverified; inline scripts/styles prevent safely enabling a restrictive CSP without compatibility work. | Verify effective production response headers and configure CSP/frame/nosniff at the app or hosting edge; enable HSTS only after HTTPS is enforced. | M |
| SEC-08 | Security / token lifecycle | Medium | `config/sanctum.php:50`; `routes/api.php:25-35` | Baseline: tokens had no expiry. Sanctum now has a 24-hour maximum and API login sets `expires_at`; password changes and reset revoke tokens. | Configure a finite expiration policy, set per-token expiry where needed, and revoke tokens on password changes, account deactivation, and suspected compromise. | S |
| SEC-09 | Security / password policy | Low | `app/Http/Controllers/AuthController.php:154-159,178-180` | Baseline: reset/registration accepted six characters. Registration, reset, admin creation, and self-service password changes now require 12 characters. | Raise the minimum and provide passphrase guidance; keep the existing password hashing and confirmation rules. | S |
| API-01 | API correctness | Medium | `app/Http/Controllers/Api/FacilityRequestApiController.php:231-242` | `PUT/PATCH /api/facility-requests/{facility_request}` remains intentionally unsupported but now returns `501` with an explicit message, not a success-shaped response; a regression test verifies no mutation. | Implement validated update semantics or return `405/501` until supported; test that submitted fields persist. | S |
| API-02 | Validation / business logic | Medium | `app/Http/Controllers/Api/FacilityRequestApiController.php:82-96,128-143,322-327` | API store now constrains venue/equipment names to active catalog entries, enforces distinct equipment selections, and validates nested quantities as positive integers; availability is rechecked under resource locks. | Validate `equipment.*` against active catalog IDs and `equipment_quantities.*` as `integer|min:1`, then re-check quantity and inventory atomically at approval. | S |
| API-03 | Validation / inventory | Medium | `app/Http/Controllers/Api/FacilityRequestApiController.php:424-485` | API return inputs are validated as non-negative integers; model-level per-item assignment, approved allocation, cumulative returned/damaged/missing bounds, duplicate returns, and inventory deltas are validated transactionally for both API and web paths. | Validate each item count against the request’s reserved quantity, reject negative/over-return values, and compute damage/missing per item before updating inventory in one transaction. | S |
| ERR-01 | Bugs & errors / observability | Low | `app/Services/DocumentUploadService.php:84-88,139-140` | Upload and deletion exceptions are now logged with safe document context while callers receive generic errors; upload-store failure is checked explicitly. | Log failures with safe request/document context and retain generic user-facing errors; do not log file contents or credentials. | S |
| BIZ-04 | Business logic / time handling | Medium | `app/Models/FacilityRequest.php:746-760`; `app/Http/Controllers/RequestorController.php:1040-1052` | Same-date invalid end times are no longer silently shifted overnight. Web/API reject non-increasing ranges; overnight booking requires an explicitly later end date. | Only roll to the next day when the user explicitly supplies a later end date/overnight duration; otherwise reject an end time at or before the start time. | S |
| BIZ-05 | Business logic / date validation | Medium | `app/Http/Controllers/RequestorController.php:457-465` | Requestor edit/reschedule now uses `after_or_equal:today`, matching creation; normalized datetime ordering is checked before persistence. | Apply the same past-date rule to edit/reschedule and validate the date/time range after normalization. | S |
| BIZ-06 | Business logic / urgent requests | Medium | `app/Http/Controllers/RequestorController.php:1054-1104,1260-1271`; `app/Http/Controllers/Api/FacilityRequestApiController.php:83-96,152-188` | Web/API urgent requests now share a 48-hour eligibility cutoff, institutional priority flag, and initial conflict deferral for human review. Urgency does not auto-approve a conflict. Only Supply Office/admin can use the explicit reason-required override; it audits the decision and places the affected request in the rescheduling workflow. | Define one urgent-request policy (cutoff, queue ordering, manual override, user feedback) and reuse a shared service in both channels. Make ordering deterministic, for example explicit priority then submission timestamp, and audit overrides. | M |
| BIZ-07 | Business logic / conflict check | Medium | `app/Http/Controllers/CalendarController.php:399-444` | Calendar preflight now checks full datetime overlap (including containing intervals) and only approved bookings, matching the availability service's conflict eligibility. | Use a canonical interval-overlap query on full datetimes (`existing_start < requested_end && existing_end > requested_start`) and share eligibility rules with `AvailabilityService`. | S |
| BIZ-08 | Business logic / inventory parity | Medium | `app/Http/Controllers/Api/FacilityRequestApiController.php:244-268`; `app/Http/Controllers/RequestActionController.php:36-57` | API cancellation now locks request/resources and inventory and records cancellation transactionally. Only pending requests can be cancelled; approved requests are rejected with `409`, matching the web's pending-only policy and preventing unsafe inventory release. | Route API cancellation through the same inventory-aware transition used by the web path; make the transition idempotent and test cancel after equipment approval. | S |
| NOT-01 | Notifications | Medium | `app/Http/Controllers/RequestActionController.php:199-238` | Equipment-custodian endorsement now reaches the shared notification path after persisting the endorsement; requestor notification behavior is covered by workflow tests. | Notify the requestor after a successful equipment endorsement and ensure every verify/reject/change transition uses one shared notification path. | S |
| UX-01 | Notifications / UX | Medium | `app/Notifications/RequestStatusChanged.php:90-105,278-347`; `resources/views/notifications/index.blade.php:14-53,87-98` | Notification payload/view now include requestor name plus activity and omit resource-list text. Mark-as-read navigation occurs only on HTTP success; a visible retry message appears on failure. | Render requestor name plus activity name (not resource-list text) and navigate only after a successful mark-as-read response; surface failures. | S |
| UX-02 | Navigation / accessibility | Medium | `resources/views/partials/header.blade.php:17,39,52,97`; `resources/views/notifications/index.blade.php:52` | Mobile notification shortcut now links correctly; menu toggle exposes/synchronizes `aria-expanded` and `aria-controls`; timestamps use a darker color. | Link the mobile shortcut correctly, synchronize menu ARIA state, and choose a timestamp color with at least 4.5:1 contrast for normal text. | S |
| PRINT-01 | Printing / UX | Medium | `resources/views/request/print.blade.php:525,969` | Print-specific layout and helper hiding are now inside `@media print`; screen preview retains its guidance. Actual browser paper-size output still needs a manual check. | Scope print-only typography/layout under `@media print`; make the helper visible in screen preview and hide it only in actual print output if appropriate. | S |
| PRINT-02 | Printing / privacy | Medium | `resources/views/requestor/show.blade.php:732-739`; `resources/views/request/print.blade.php:1116-1125` | E-signature image is removed from request details and remains on the authorized print page; regression coverage checks both surfaces. | Remove the image from request details; keep the authorized signature image restricted to the print route and retain owner/role authorization. | S |
| QA-01 | Tests / equipment policy | Low | `tests/Feature/RequestDocumentUploadTest.php:255-278`; `database/migrations/2026_09_25_000001_deactivate_aircon_equipment.php:14`; `app/Http/Controllers/RequestorController.php:47-48,65-67` | The baseline suite failure was resolved by aligning the regression test with the migration's deactivated-Aircon policy and making unsupported submitted selections fail validation rather than silently disappear. Cooler-fan backup policy remains covered. | Decide whether inactive Aircon should be requestable; then align the test and request form with the catalog policy, retaining the cooler-fan backup case. | S |
| PERF-01 | Performance | Medium | `app/Services/AvailabilityService.php:80-104,127-146` | Availability now filters approved reservations by datetime overlap in SQL before calculating resource conflict/outstanding quantities. Schedule/request date indexes exist; production query plans/load were not measured. | Move interval overlap and outstanding quantity aggregation into indexed database queries; add query-count/performance tests with a large fixture. | M |
| PERF-02 | Performance | Medium | `app/Http/Controllers/RequestorController.php:92-105,145-156` | The requestor dashboard still loads the owner's full request history and filters upcoming/past/active/completed groups in memory. The Supply Office dashboard queue now uses SQL pagination; the requestor dashboard remains unpaginated. | Paginate the requestor's primary history while preserving its search, filters, sorting, and status summaries; avoid loading all history for dashboard counts. | M |
| DB-01 | Database / operations | Medium | `database/migrations/2026_03_21_104454_create_facility_requests_table.php:14-49`; `config/database.php:20-52`; `phpunit.xml:25-36` | Schema uses migrations and in-memory SQLite tests. Live migration state and MySQL lock behavior were not verifiable because the configured MySQL host did not resolve. Provider-neutral backup/restore and rollback guidance is now in `docs/OPERATIONS.md`, but must be exercised by the operator. | Verify migrations in a reachable staging DB; document backup-before-migrate, restore drills, migration ownership, and rollback/forward-fix procedures. | M |
| CQ-01 | Code quality | Low | `app/Http/Controllers/RequestorController.php:1-1653`; `app/Http/Controllers/SupplyOfficeController.php:1-1278`; `app/Http/Controllers/CustodianController.php:1-851` | Large controllers combine query building, validation, uploads, inventory, notifications, and state transitions. Web and API paths repeat reservation and approval rules, which has already produced behavior divergence. | Extract shared reservation validation/availability and workflow transition services with focused controller tests; split by feature only as changes are made. | L |
| DOC-01 | Documentation | Medium | `README.md`; `.env.example:1-71`; `composer.json:24-56` | README setup/API/role guidance and provider-neutral operations, backup, and rollback documentation have been added. Hosting-specific runtime requirements still need operator confirmation. | Add setup/troubleshooting instructions, API/role matrix, environment variable descriptions (names only), migration/backup runbook, and deployment instructions. | M |
| DEV-01 | CI/CD | Medium | `.github/workflows/ci.yml:1-48` | CI now blocks on full Composer/npm audits, lints PHP syntax, smoke-tests SQLite migrations, tests, builds, and verifies route cache. MySQL integration, deployment, monitoring, and error tracking remain absent. | Make full-tree audits blocking; add lint/static checks and database migration smoke tests; document staging promotion, rollback, monitoring, and error reporting. | M |

## Critical and High remediation examples

No Critical finding was confirmed. The following snippets record implementation patterns for High findings identified at baseline; all five High findings (SEC-01, SEC-02, BIZ-01, BIZ-02, BIZ-03) are now remediated. Snippets remain as rationale and maintainer reference.

### SEC-01 — Validate and safely serve legacy proposals

```php
$request->validate([
    'proposal_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
]);

// For retrieval, prefer an attachment response from private storage:
return Storage::disk('local')->download($filePath, basename($filePath), [
    'X-Content-Type-Options' => 'nosniff',
]);
```

Do not use a client-supplied extension as proof of file type; ensure the path is generated server-side and is not publicly symlinked.

### SEC-02 — Enforce inactive-account state on every authenticated request

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if ($user && ! $user->is_active) {
        if ($token = $user->currentAccessToken()) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        abort(403, 'This account is inactive.');
    }

    return $next($request);
}
```

Apply the guard to both web and Sanctum auth flows; also reject inactive Google accounts before `Auth::login()` and revoke existing sessions/tokens when admins deactivate users.

### BIZ-01 — Require the custodial stages before calendar final approval

```php
if ($user->isAdmin() && ! $type) {
    abort_unless(
        $facilityRequest->status === 'pending'
        && $facilityRequest->venue_status === 'approved'
        && $facilityRequest->equipment_status === 'approved',
        409,
        'Required custodian endorsements are incomplete.'
    );
}
```

Prefer invoking the existing final-approval transition/service instead of duplicating the status writes in `CalendarController`.

### BIZ-02 — Serialize checks for the same venue

```php
DB::transaction(function () use ($facilityRequest): void {
    $venueIds = $facilityRequest->requestVenues()
        ->orderBy('venue_id')
        ->pluck('venue_id')
        ->filter();

    Venue::whereKey($venueIds)->orderBy('id')->lockForUpdate()->get();

    if ($message = $this->availabilityService->checkFacilityRequest(
        $facilityRequest,
        $facilityRequest->id
    )) {
        throw ValidationException::withMessages(['availability' => $message]);
    }

    // Persist the approval while holding the shared venue locks.
});
```

All approval and override entry points must acquire the same stable locks before checking and writing; checking each request row independently is not sufficient.

### BIZ-03 — Treat physical free stock as authoritative

```php
$available = max(0, (int) $equipment->quantity_available);

return [
    'available' => $available >= $quantity,
    'available_qty' => $available,
    'total' => (int) $equipment->quantity,
];
```

Validate per-item return/damage/missing counts against the approved quantity and restore only returned, usable units. Keep a single source of truth so an `fulfilled` status cannot make missing or damaged stock available again.

## Step 1 — Audit categories and route inventory

### 1. Bugs & errors

The baseline defects `API-01`, `API-02`, `ERR-01`, and `QA-01` have been corrected: unsupported API updates report `501`, nested quantities/catalog inputs are validated, upload errors are logged safely, and the former catalog-policy test mismatch is covered. Requestor edits now reject past starts and same-date inverted ranges. Web/API validation still has duplicated implementation that should eventually be consolidated.

### 2. Business logic

- At audit baseline, a direct admin calendar action could skip custodian stages (`BIZ-01`); the calendar path now delegates to the guarded Supply Office final-approval workflow.
- Approval/submission paths use shared deterministic resource lock rows (`BIZ-02`), and calendar preflight now checks approved full-datetime overlaps (`BIZ-07`). The SQLite test driver cannot prove concurrent MySQL row-lock behavior.
- Queues use deterministic date/time/creation ordering; requestors may opt into latest-first for their own list. Urgent submissions have an explicit institutional priority and 48-hour cutoff across web/API. They remain human-reviewed; only the admin/Supply Office priority override requires a reason and records a conflicting request for rescheduling (`BIZ-06`).
- Time normalization no longer converts same-date inverted times to overnight bookings; edit and create paths reject past or non-increasing schedules (`BIZ-04`, `BIZ-05`).
- Itemized return accounting and inventory reconciliation prevent damaged/missing/partial stock from being counted as usable (`BIZ-03`). Cooler-fan backup policy remains covered. Aircon remains deactivated per migration; regression tests now reflect that catalog policy (`QA-01`).

### 3. Security

The nine baseline source findings SEC-01 through SEC-09 are remediated in the current checkout: private validated uploads, active-account enforcement, minimal calendar/custodian list DTOs, web/API login throttles, baseline security headers, finite/revoked API tokens, and a 12-character password minimum. Composer and full npm audits report no advisories. An ignored local `.env.production` exists, but active status/exposure and production response headers require operator verification; CSP is not added because the app contains inline scripts/styles and compatibility was not established.

### 4. API endpoints & routes

The table below inventories **all 156 routes reported by `php artisan route:list`**, grouped where routes share the same guard and controller/response behavior. Middleware is from route declarations; controllers may impose additional per-record policy checks. For web routes, Laravel validation normally redirects with session errors; for JSON routes it normally returns `422`. Success statuses/redirects are called out where they are materially different.

| Methods and paths | Auth / allowed roles | Validation and response behavior | Source |
|---|---|---|---|
| `GET|HEAD /`; `GET|HEAD /calendar`; `GET|HEAD /calendar/events` | Public; calendar events public | HTML/JSON success; public and requestor event payloads use a reduced DTO for other users' requests. Owners and administrators receive their authorized detail payload. | `routes/web.php:31-58,199` |
| `GET|HEAD /login`, `/forgot-password`, `/reset-password/{token}`, `/auth/google/redirect`, `/auth/google/callback`, `/register`, `/register/departments/{college}`, `/register/verify`; `POST /login`, `/forgot-password`, `/reset-password`, `/register`, `/register/verify`, `/register/verify/resend`, `/logout` | Public guest/session flows | Login/register/password/OTP validation in `AuthController`; success redirects or OAuth response; validation redirects; invalid login is rejected. | `routes/web.php:36-59`; `app/Http/Controllers/AuthController.php` |
| `GET|HEAD /up`; `GET|HEAD /sanctum/csrf-cookie`; `GET|POST|HEAD /broadcasting/auth` | Framework health, CSRF, and channel authorization | Framework-defined response; broadcasting authorization relies on channel callbacks. | `routes/api.php`; `routes/channels.php` |
| `GET|HEAD /storage/{path}`; `PUT /storage/{path}` | No auth middleware; Laravel requires a valid time-limited relative signature for the private local disk. `PUT` additionally requires `upload=true`. | Signed private-file read/upload; framework enforces signature and expiry. No vulnerability confirmed in these routes. | Laravel `storage.local` / `storage.local.upload`; `config/filesystems.php:33-37` |
| `POST /api/login` | Public; 10 requests/minute/IP | Username/password validation; `200` token with 24-hour expiry or generic `401` invalid credentials. | `routes/api.php:25-44` |
| `GET /api/user`; `POST /api/logout` | Sanctum authenticated | `200`; logout deletes current token when present. | `routes/api.php:20-50` |
| `GET /api/reservations` | Public | Calendar JSON; public DTO omits requestor PII and internal workflow details. | `routes/api.php:53`; `CalendarController@getEvents` |
| `GET /api/facility-requests`; `POST /api/facility-requests`; `GET /api/facility-requests/{facility_request}`; `PUT|PATCH /api/facility-requests/{facility_request}`; `DELETE /api/facility-requests/{facility_request}`; `POST /api/facility-requests/{facility_request}/approve`, `/reject`, `/cancel`, `/return-equipment`; `GET /api/equipment/availability`, `/api/venue/availability` | Sanctum authenticated and active, then policy/controller authorization; owner/custodian/admin behavior depends on action and resource assignment | `store` returns `201` or validation/availability `422`; stage actions can return `409`, `422`, or `500`; unsupported update returns explicit `501`; pending-only cancel records status/history and is transactional. | `routes/api.php:56-64`; `FacilityRequestApiController` |
| `GET /api-test/facility-requests`; `POST /api-test/facility-requests`; `GET /api-test/facility-requests/{facility_request}`; `PUT /api-test/facility-requests/{facility_request}`; `DELETE /api-test/facility-requests/{facility_request}`; `POST /api-test/facility-requests/{facility_request}/approve`, `/reject`, `/cancel`, `/return-equipment`; `GET /api-test/equipment-availability`, `/api-test/venue-availability` | Web session authenticated; action-level controller/policy checks | Same controller behavior as API resource, but session/CSRF semantics; this is a duplicate legacy surface. | `routes/web.php:16-28` |
| `GET|HEAD /requestor`, `/requestor/dashboard`, `/requestor/settings`, `/requestor/equipment/availability`; `GET|HEAD /requestor/requests/{facilityRequest}/edit`; `POST /requestor/store`, `/requestor/delete`, `/requestor/settings/profile`, `/requestor/settings/password`, `/requestor/settings/notifications`, `/requestor/settings/signature`; `PUT /requestor/requests/{facilityRequest}` | Authenticated `requestor` role | Requestor form validation/availability and ownership checks; success redirects; validation returns redirect errors. Edit is owner/status restricted. | `routes/web.php` requestor group; `RequestorController` |
| `GET|HEAD /custodian`, `/custodian/dashboard`, `/custodian/assignments`, `/custodian/venue`, `/custodian/equipment`, `/custodian/settings`, `/custodian/equipment/availability`; `POST /custodian/update`, `/custodian/facility-request/{id}/return`, `/custodian/venues`, `/custodian/equipment`, `/custodian/settings/profile`, `/custodian/settings/password`, `/custodian/settings/notifications`, `/custodian/settings/signature`, `/custodian/equipment/{equipment}/report-issue`, `/custodian/equipment/{equipment}/return`; `PUT /custodian/venues/{venue}`, `/custodian/equipment/{equipment}`; `PATCH /custodian/venues/{venue}/toggle`, `/custodian/equipment/{equipment}/toggle` | Authenticated `custodian` role; controller checks assigned venue/equipment and abilities | Validation redirects on invalid forms; forbidden/unassigned operations reject; successful actions redirect. | `routes/web.php` custodian group; `CustodianController` |
| `GET|HEAD /supply-office`, `/supply-office/dashboard`, `/supply-office/audit-logs`, `/supply-office/calendar`, `/supply-office/usage-reports`, `/supply-office/requests/pending`, `/supply-office/requests/final-approval`, `/supply-office/requests/approved`, `/supply-office/requests/rejected`, `/supply-office/requests/needs-reschedule`, `/supply-office/requests/returns`, `/supply-office/final-approval`, `/supply-office/settings`, `/supply-office/organizations`, `/supply-office/users`; `POST /supply-office/delete`, `/supply-office/update`, `/supply-office/requests/needs-revision`, `/supply-office/requests/revise`, `/supply-office/settings/profile`, `/supply-office/settings/password`, `/supply-office/settings/notifications`, `/supply-office/settings/signature`, `/supply-office/organizations`, `/supply-office/organizations/{organization}/memberships`, `/supply-office/priority-override/confirm`, `/supply-office/users`; `PUT /supply-office/organizations/{organization}`, `/supply-office/organization-memberships/{membership}`, `/supply-office/users/{user}`; `DELETE /supply-office/users/{user}`; `POST /supply-office/users/{user}/reactivate` | Authenticated `admin` role | Views return HTML; mutation validation generally redirects; final approval/priority override handlers have state checks. | `routes/web.php` Supply Office group; `SupplyOfficeController`, `AdminController` |
| `GET|HEAD /admin`, `/admin/audit-logs`, `/admin/calendar`, `/admin/final-approval`, `/admin/reports`, `/admin/settings`, `/admin/users`; `POST /admin/delete`, `/admin/update`, `/admin/settings/profile`, `/admin/settings/password`, `/admin/settings/notifications`, `/admin/settings/signature`, `/admin/users`; `PUT /admin/users/{user}`; `DELETE /admin/users/{user}`; `POST /admin/users/{user}/reactivate`; `GET|HEAD /admin/export` | Authenticated `admin` role | Duplicated legacy admin alias surface; controller-specific validation/redirect behavior. | `routes/web.php` admin group |
| `GET|HEAD /notifications`; `POST /notifications/{id}/read` | Authenticated any role; per-notification ownership check | HTML list and mark-read redirect/JSON behavior; failed client request is not surfaced (`UX-01`). | `routes/web.php` notification group; `NotificationController` |
| `GET|HEAD /user/{user}/signature`; `GET|HEAD /request/{id}`, `/request/{id}/print`, `/request/{id}/proposal`, `/request/{id}/proposal/download`, `/request/{id}/signature`, `/request/{facilityRequest}/approval-signature/{type}` | Authenticated; request policy, owner/admin/signature authorization as applicable | At baseline, proposal preview served files inline (`SEC-01`); now restricted to allowlisted types with `nosniff`. | `routes/web.php` shared authenticated group; `RequestorController` |
| `POST /request/{facilityRequest}/cancel`, `/request/{facilityRequest}/custodian/verify`, `/request/{facilityRequest}/custodian/reject`, `/request/{facilityRequest}/custodian/revision`, `/request/{facilityRequest}/change-request`, `/request/{facilityRequest}/change-request/approve`, `/request/{facilityRequest}/change-request/reject`, `/request/{facilityRequest}/supply/final-approval`, `/request/{facilityRequest}/supply/decline` | Authenticated any role at middleware; controller requires request owner, assigned custodian, or admin depending on action | Form validation and redirects; final approval checks endorsements; equipment verify notification gap (`NOT-01`). | `routes/web.php` shared request-action group; `RequestActionController` |
| `POST /calendar/return/{id}`, `/calendar/approve/{id}`, `/calendar/reject/{facility_request}`; `POST /calendar/check-conflicts` | Authenticated; check-conflicts adds `role:admin`; actions additionally authorize admin/assigned custodians | Final admin calendar approval delegates to the Supply Office guarded workflow and returns JSON for JSON clients; custodian-stage approvals remain role/resource checked. Approval availability checks serialize on shared resource rows (`BIZ-02`). Conflict preflight still has overlap gap (`BIZ-07`). | `routes/web.php:201-207`; `CalendarController`, `RequestActionController`, `CustodianController`, `FacilityRequestApiController` |

**Route findings:** Most role dashboards are protected at group level, but shared request routes use broad `auth` middleware and rely on the controller/policy to enforce every action. `/admin/*` and `/supply-office/*` provide overlapping legacy administrative surfaces; `/api-test/*` duplicates Sanctum-style operations under session authentication. Consolidate these after compatibility checks so state transitions and validation cannot drift.

### 5. Notifications

Equipment endorsement now reaches the shared requestor notification path (`NOT-01`). Notification rows display the requestor/activity pair; mark-as-read failures remain on the page with visible feedback rather than navigating (`UX-01`). Other transition notification paths still warrant complete end-to-end delivery checks.

### 6. UI/UX & accessibility

The mobile notification link now works; menu state is exposed via ARIA; timestamp contrast is improved, and notification failure feedback is visible (`UX-01`, `UX-02`). The source review did not confirm a broken Final Approval detail link, duplicated “View my request” button, missing quantity stepper, or incorrect history status icon; this is not a pixel-level/browser sign-off. Repository-wide contrast, responsive rendering, and confirmation dialogs still require browser review.

### 7. Printing & documents

Print templates and signature lifecycle tests exist (`tests/Feature/Phase7AdminSearchPrintingTest.php`, `tests/Feature/VisualSignatureLifecycleTest.php`). Request details no longer render the e-signature; it remains in the authorized print view (`PRINT-02`). Print-only CSS is now scoped to `@media print`, setup guidance is visible in the screen preview, and the redundant approval-slip placeholder was removed in favor of the existing direct print action (`PRINT-01`). Source does not establish a legally binding consent/identity verification process or trusted timestamp integrity. Production paper output still needs browser/PDF verification.

### 8. Database

The schema is represented through 78 Laravel migrations, with relational `request_venues`, `request_equipment`, `reservation_schedules`, request history, reminder, and user/org tables. Common status/date/owner indexes are added by migrations; several relations use foreign keys. The test database is in-memory SQLite. The configured live MySQL host previously failed DNS resolution during `migrate:status`, so applied migration state, live constraints, seed correctness, backup policy, and production data integrity remain unverified (`DB-01`). No active backup job or restore procedure could be verified from the repository.

### 9. Code quality & tests

Main behavior spans very large controllers (`CQ-01`) and duplicates between API, calendar, and browser form actions. The `StoreReservationRequest` FormRequest exists but the requestor create route accepts the base `Request` and validates inline, so that request class does not define the active create contract. The test suite includes authorization, workflow, availability, uploads, notifications, print, and schema coverage.

**Baseline test run:** `php artisan test --compact` — **325 passed, 1 failed, 1,321 assertions**. Failure: an Aircon test contradicted the deactivation migration. This baseline defect was subsequently resolved; see current disposition below. PHPUnit still reports deprecated doc-comment metadata in legacy print/signature tests.

**Critical-flow test plan:** Add regression tests for admin/calendar final approval before custodian endorsements; two simultaneous overlapping approvals on the same venue/equipment; partial, damaged, missing, and over-reported equipment returns; inactive web sessions and Sanctum tokens after account deactivation plus Google OAuth for inactive accounts; invalid and oversized legacy uploads with HTML served as attachment; API update persistence and nested quantity bounds; notification delivery/content for each custodian endorsement/rejection; and date/time boundary cases including past reschedules and same-day end-before-start.

### 10. CI/CD & DevOps

The GitHub Actions workflow runs PHP 8.2/Node 20 setup, full blocking Composer/npm audits, PHP syntax lint, SQLite migration smoke test, PHP tests, Vite production build, and route cache. No MySQL migration/concurrency service, deployment, staged promotion, rollback automation, monitoring, or error tracking is configured (`DEV-01`). Both local dependency audits currently report zero advisories.

### 11. Performance

`AvailabilityService` now selects approved reservations overlapping the requested datetimes in SQL before its remaining per-item calculations (`PERF-01`). The Supply Office review queue is now paginated in SQL with query parameters preserved, and summary counts use database counts. The requestor dashboard still loads and filters the full request history in memory (`PERF-02`). Bundle size, image payloads, and browser re-render cost were not measured.

### 12. Documentation

`README.md`, `docs/API.md`, and `docs/OPERATIONS.md` now document local setup, principal API behavior/roles, deployment boundaries, secret handling, migration, backup/restore, smoke checks, and rollback guidance (`DOC-01`). Hosting-provider-specific settings still require confirmation by the operator.

## Quick wins

The source-level quick wins API-01/02, API-03, BIZ-04/05/06/07/08, ERR-01, NOT-01, UX-01/02, PRINT-01/02, QA-01, DOC-01, SEC-06/08/09, and DEV-01 have been implemented in this remediation pass. Remaining near-term actions require an operator or staging environment rather than another local code edit: inspect/rotate any active deployment secrets; run migrations and simultaneous-booking tests on staging MySQL; verify backup restoration, HTTPS/HSTS, and printed paper output.

## Must fix before defense/deployment

1. Establish whether ignored local production-looking credentials are active or have been shared; rotate as required and move secrets to managed hosting configuration (`SEC-05`).
2. Apply all migrations in a staging database matching production and verify the new resource-lock table exists (`DB-01`).
3. Exercise simultaneous venue/equipment approvals against the production database engine; SQLite tests do not validate lock contention (`BIZ-02`).
4. Verify the provider serves only `public/`, private uploaded files are inaccessible without authorization, HTTPS is enforced, and actual response headers are present.
5. Perform and document a protected backup/restore drill, then run release smoke checks from `docs/OPERATIONS.md`.

## Prioritized roadmap

| Priority | Phase | Work |
|---|---|---|
| P0 — before deployment | External security and database verification | Assess/rotate potentially active secrets; apply migrations and verify concurrency on staging MySQL; verify HTTPS/headers and private storage; complete a backup restore drill. |
| P1 — release readiness | Smoke tests and production controls | Run the full suite and deploy smoke checklist against staging; verify role-specific approval, urgent override reason, cancellation, returns, notification, uploads, signatures, and printed form. |
| P2 — scale/maintenance | Performance and code quality | Paginate high-volume dashboards without losing filter/tab behavior; measure MySQL plans; extract duplicated workflow validation from large controllers; add query-count/load tests. |
| P3 — platform maturity | CI/operations | Add MySQL-backed CI, deployment promotion/rollback automation, application monitoring/error tracking, and hosting-specific runbooks once the provider is known. |

## Post-audit remediation status

**SEC-01 — remediated in the user-authorized follow-up.** Legacy `proposal_file` now has server-side PDF/JPEG/PNG and 10 MB validation (`RequestorController.php:858`), uses `DocumentUploadService` to generate a private filename and store under `documents/proposal_file/` (`1172-1184`), and no longer trusts the client extension for a stored path. Authorized proposal preview uses an explicit allowlist and `X-Content-Type-Options: nosniff` (`1479-1525`); unsupported extensions are downloaded as attachments. Historical local `proposals/` files remain readable, the previous public-disk fallback is retained for compatibility, and path separators in historical filenames are rejected (`RequestorController.php:1547-1574`). Download remains authorized and attachment-only (`1529-1545`). Regression coverage rejects HTML and traversal filenames, checks safe PDF preview/download headers, confirms legacy HTML is forced to attachment, and exercises the historical public-disk fallback (`RequestDocumentUploadTest.php:255-320`).

**Verification:** `php artisan test --compact --filter=legacy_proposal_upload_rejects_html_and_serves_approved_types_safely` passed (1 test, 28 assertions). The complete `RequestDocumentUploadTest` file had 19 passes and one unrelated pre-existing Aircon catalog-policy failure (`test_student_can_request_aircon_for_balay_alumni`, undefined `Aircon` quantity); do not interpret that failure as caused by SEC-01.

**SEC-02 — remediated for the repository's default database session driver in the user-authorized follow-up.** `EnsureActiveAccount` is now appended to the web middleware group and applied after Sanctum authentication to the API user, logout, and facility-request routes. Inactive browser sessions are logged out and invalidated; inactive API requests receive JSON `403` and their current token is deleted. Google OAuth refuses to authenticate an inactive linked account or attach a Google identity to an inactive account. Both admin deactivation paths now revoke the user's Sanctum tokens and, when the configured session driver is `database` (the repository default), delete persisted sessions for that user. If deployment config selects another session backend, active sessions are denied while the account remains inactive, but sessions cannot be proactively enumerated/purged by this implementation and an idle session could resume after reactivation; add backend-specific revocation if an alternate driver is used.

**Verification:** `php artisan test --compact tests/Feature/AccountLifecycleTest.php tests/Feature/AuthFlowTest.php tests/Feature/AuthorizationTest.php` passed (19 tests, 67 assertions), covering web-session rejection, API token rejection/revocation, both Google callback cases, and admin deactivation revocation of tokens and database sessions.

After the SEC-02 follow-up, the full suite completed with **331 passed, 1 failed, 1,371 assertions**. After the BIZ-01 follow-up, it completed with **334 passed, 1 failed, 1,394 assertions**. After BIZ-02 and the resource-lock migration, it completed with **338 passed, 1 failed, 1,412 assertions**. The sole failure remains the baseline Aircon catalog-policy mismatch documented above; no new failure was observed.

**BIZ-01 — remediated in the user-authorized follow-up.** Calendar admin final approval no longer updates status or marks custodian stages directly. It rejects typed admin stage approvals and delegates untyped final approval to `RequestActionController::supplyFinalApproval`, reusing its custodian-state guard, availability check, approval signature, history entry, and requestor notification. Calendar JSON clients receive JSON success/conflict responses from the shared workflow; the legacy calendar action now requests JSON explicitly.

**Verification:** `php artisan test --compact tests/Feature/ApprovalWorkflowTest.php` passed (9 tests, 53 assertions), including separate missing-venue and missing-equipment cases, blocking admin attempts to set custodian stages, and successful calendar final approval with the canonical history/notification. The full `php artisan test --compact` suite reported 334 passed and the same 1 pre-existing Aircon catalog-policy failure (1,394 assertions) after this change.

**BIZ-02 — remediated in the user-authorized follow-up.** A new `reservation_resource_locks` table stores one hashed lock row per normalized venue/equipment name, including resource names that do not have a catalog row. `AvailabilityService::lockResourcesForFacilityRequests()` requires an active transaction, creates any missing lock rows, and locks participating rows in deterministic type/hash order. Web/API submissions now repeat their availability check after acquiring shared resource locks and before inserting. Custodian verification, API custodian approvals, Supply Office final approval, and delegated calendar approval acquire those same locks before locking/updating the request and checking availability. Supply Office priority override locks the resources for both affected requests before changing either state.

**Verification:** `php artisan test --compact tests/Feature/ApprovalWorkflowTest.php tests/Feature/Phase2AvailabilityPolicyTest.php tests/Feature/FacilityRequestWorkflowTest.php` passed (27 tests, 130 assertions), covering venue and equipment approval conflicts, lock row creation for catalog and missing-catalog resource names, and the existing availability policy. The full `php artisan test --compact` suite reported **338 passed, 1 failed, 1,412 assertions**. Its only failure is the same pre-existing Aircon catalog-policy mismatch. The test database is in-memory SQLite, where `lockForUpdate()` does not provide production-grade row locking; live MySQL was unavailable during the baseline audit. Thus simultaneous execution still needs verification against the deployed database engine before release.

**BIZ-03 — remediated in the user-authorized follow-up.** Return recording is now shared by web and API workflows through `FacilityRequest::markEquipmentReturned()`. It rejects unapproved, premature, unassigned, malformed, and over-quantity returns; stores cumulative per-item returned/damaged/missing counts; restores only newly returned, usable units; and completes a request only after every requested unit is returned or explicitly recorded missing. Availability calculations and `equipment:sync-availability` use the remaining per-item usable-inventory commitment so a completed return cannot restore damaged or missing units during a rebuild. Historical records with aggregate loss totals but no per-item condition data are handled conservatively and do not cause presumed stock restoration. Return eligibility now checks the scheduled end datetime rather than the date-only midnight boundary. This change does not add a repair or found-item recovery flow; damaged/missing stock remains unavailable until reconciled through inventory management.

**Verification:** `php artisan test --compact tests/Feature/InventoryConsistencyTest.php tests/Feature/SecurityHardeningTest.php` passed (17 tests, 68 assertions), covering mixed equipment quantities with damage and loss, partial and duplicate returns, over-quantity and premature-return rejection, API duplicate protection, the availability rebuild, and existing authorization controls. The full suite at that point reported **342 passed, 1 failed, 1,440 assertions**. The Aircon failure was an outdated test conflicting with the migration that deactivates Aircon and the request form that no longer offers it. The requestor endpoint was also silently discarding unsupported selections; it now rejects them with a validation error instead of submitting a request with the equipment omitted. The regression test now asserts this behavior.

**Aircon follow-up verification:** `php artisan test --compact` now passes with **343 tests and 1,442 assertions**. The request regression test verifies that a submitted deactivated item produces an equipment validation error and does not create the request.

## Current remediation disposition

This section supersedes earlier baseline/follow-up status snapshots above.

| Disposition | Findings |
|---|---|
| Remediated in source and covered by tests or checks | SEC-01, SEC-02, SEC-03, SEC-04, SEC-06, SEC-07 (application headers only), SEC-08, SEC-09; BIZ-01 through BIZ-08; API-01, API-02, API-03; ERR-01; NOT-01; UX-01, UX-02; PRINT-01, PRINT-02; QA-01; DEV-01 (repository CI checks); DOC-01 |
| Partially remediated / needs scale or architecture work | PERF-01 (overlap candidates now filtered in SQL, but no production query plan/load benchmark); PERF-02 (Supply Office queue paginated; requestor history dashboard remains unpaginated); CQ-01 (controller decomposition remains a larger refactor) |
| External operator/staging action required | SEC-05 (determine whether ignored local production-looking credentials are active or exposed; rotate if needed); DB-01 (verify all migrations/constraints on staging MySQL and complete backup/restore drill); SEC-07 (verify deployed HTTPS, HSTS, CSP compatibility, CORS, and host-edge headers); BIZ-02 (exercise contention using the production database engine) |

**Latest verification:** `php artisan test --compact` — **353 passed, 1,487 assertions**, including the Supply Office dashboard pagination regression test. `php artisan view:cache` succeeded and compiled views were cleared afterward. A full in-memory SQLite `migrate:fresh` migration smoke test passed. PHP syntax lint passed for 222 files. `npm run build`, `php artisan route:cache`, and `php artisan view:cache` passed (generated caches were cleared afterward). `composer audit --no-interaction` and `npm audit --audit-level=high` both reported zero known vulnerabilities. `git diff --check` passed before the latest pagination edits; PHPUnit still emits deprecated doc-comment metadata warnings for legacy tests.

**Release caveat:** The source-level remediation is not equivalent to a production deployment sign-off. Do not consider the task fully operationally closed until the external checks above are completed on the actual host and matching MySQL version.
