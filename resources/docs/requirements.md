# PITFR Venue & Equipment Reservation - Requirements (from panel meeting)

## Summary
Panel prioritized support for outsiders, accurate inventory, custodian routing, and clear accountability for requestors.

## Key Decisions
- Outsiders are allowed to book; mark them with `is_outsider` and store affiliation.
- Requests route to the designated custodian(s) for the venue/equipment; custodians approve/decline in the system.
- Venue selection must be single-choice (radio) and conflicting venues should be disabled for the chosen date/time.
- Inventory must list explicit items (no generic "others" for equipment inventory). "Others (specify)" is allowed at request time but not in canonical inventory.
- Offices may have one account but every request must record an individual `requisitioner_name` and `requested_by_id` for accountability.
- Activity proposal upload is supported; supply office may require it for some request types.
- Digital signatures must be verified (not just pasted images).
- Printable request form must reflect selected/approved items and totals.
- Enforce equipment quantity <= available at submit-time and update availability atomically.

## System Requirements (implementation notes)
- Models: `Venue`, `Equipment`, `FacilityRequest`, `RequestHistory` with fields described in meeting.
- Inventory: `equipment` table with `name, quantity, quantity_available, custodian_id`.
- Availability: API endpoint `equipment.availability` already exists; frontend must call it after date/time changes.
- Conflict check: POST to `calendar.check-conflicts` with single `venue` value and date/time range.
- UI: `resources/views/requestor/partials/request_form.blade.php` updated to use radio buttons for venue and client-side disabling of conflicting venues.
- Seed: `VenueAndEquipmentSeeder` to populate initial venues and equipment.

## Action Items & Owners
- Proponents / Devs: Ensure canonical equipment inventory (no "others"); seed DB. (done: `VenueAndEquipmentSeeder`) 
- Proponents / Devs: Sync availability regularly and on-demand via `php artisan equipment:sync-availability`. (done)
- Proponents / Devs: UI change - venue radios and conflict disabling (done: `request_form.blade.php`).
- Supply Office / Custodians: Provide canonical equipment list (types, counts, custodians) to seed DB.
- Panel / Stakeholders: Confirm which requests require activity proposals and exceptions (e.g., emergencies).
- QA / Technical Writer: Update orientation docs and user flow (Dangel).

## How to run locally (quick)

Run migrations (if any):
```bash
php artisan migrate --force
```
Seed venues & equipment:
```bash
php artisan db:seed --class=VenueAndEquipmentSeeder
```
Sync availability:
```bash
php artisan equipment:sync-availability
```
Run tests related to availability/workflow:
```bash
php artisan test --filter FacilityRequestWorkflowTest
php artisan test --filter EquipmentAvailabilityBadgeTest
```

---
Generated: 2026-06-20
