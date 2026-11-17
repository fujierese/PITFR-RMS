# Issue Register audit pass

Each item from `PITFR-RMS_System_Issues_and_Fix_Register.docx` checked against the code (branch `main`, commit `4be3f5c`).

Status: **Confirmed** (problem exists as described) · **Partly** (some of it is already handled) · **Done** (already fixed) · **Not code** (needs a decision or non-code work) · **New** (found during the audit, not in the register).

## 4.1 Registration and accounts

| ID | Status | Evidence | What is left |
|---|---|---|---|
| REG-01 | Confirmed | `auth/register.blade.php:54` and `welcome.blade.php:297` say only Outsiders can register. `AuthController::register` hard-codes `requestor_type = 'outsider'`. | Text change is easy. Letting students/faculty/staff self-register is a policy change that depends on D4 (verification), because a self-chosen category would unlock non-outsider rules. |
| REG-02 | Confirmed | No asterisks on any label. Required fields: first name, surname, email, organization name, organization type, password. | Add asterisks. |
| REG-03 | Confirmed | Organization name and type are `required` (server and form); acronym is `(optional)`. Exactly the reverse of what the panel asked. | Swap: make name and type optional, show acronym for all. Note NEW-02 below. |
| REG-04 | Partly | Server already requires **12** characters (`AuthController.php:224, 298`), not 6. No strength meter, no mixed-character rule. | Add meter and mixed-character rule. Fix the placeholder (NEW-01). |
| REG-05 | Confirmed | Self-registration always creates an Outsider, so a faculty member can't claim a category by registering. The real gap is the reverse: nothing proves an admin-created student/faculty account belongs to the person using it, and nothing re-checks category at payment. | Blocked by D4. |
| REG-06 | Confirmed | No privacy acknowledgment on the registration form or in validation. | Blocked by D6 for the wording; the checkbox itself is easy. |
| REG-07 | Partly | Web submission already validates student organization membership on the server against active memberships (`RequestorController.php:1490-1506`). Problems: an invalid organization ID silently falls back to the user's first membership instead of failing, and see NEW-03. Draft-context change is not re-confirmed. | Fail on an invalid ID; fix NEW-03; add the draft re-confirmation. |
| REG-08 | Confirmed | `requestor_type` is student/faculty/staff/outsider; "student organization" is a request context, "office" is free text in `office_or_organization`. No single taxonomy. | Blocked by D7. |

### Found during the audit

| ID | Severity | Problem |
|---|---|---|
| NEW-01 | Medium | The registration password placeholder says "At least 6 characters" but the server rejects anything under 12, so users hit a confusing error. |
| NEW-02 | High | The organization field suggests typing "Individual / Personal", and registration then stores the organization as empty. Request submission refuses Outsiders with an empty organization (`RequestorController.php:1550-1553`: "Please update your profile organization/office"). An Outsider who follows the hint cannot submit a request until they edit their profile. Found by reading the code; not yet reproduced. |
| NEW-03 | Medium | For students, a second block (`RequestorController.php:1558-1563`) always replaces the chosen organization with the user's *first* active membership. A student in two organizations cannot submit for the second one. Found by reading the code; not yet reproduced. |
| NEW-04 | High (for APR-01) | Request submission requires the **requestor** to have an e-signature file (`RequestorController.php:1485-1488`). Removing signature uploads (APR-01) must also remove this rule, or nobody can submit a request. |

### 4.1 fixes (branch `fix/registration-module`)

| ID | Result |
|---|---|
| NEW-02 | **Fixed.** Outsiders with no organization submit as "Individual / Personal" (form and server). Test: `test_individual_outsider_without_organization_can_submit`. |
| REG-02 | **Fixed.** Red asterisk on every required field, "(optional)" on the rest, and a "Fields marked * are required" note. |
| REG-03 | **Fixed.** Organization name and type are optional (form and server); the acronym stays visible and optional. Test: `test_individual_can_register_without_organization_name_or_type`. |
| REG-04 + NEW-01 | **Fixed.** One policy for registration, password reset and every settings page: at least 12 characters with upper- and lower-case letters, a number and a symbol. Live strength meter (weak / moderate / strong) on registration; hints updated on reset, settings and admin user editing. Test: `test_registration_rejects_passwords_without_mixed_characters`. Existing accounts keep their passwords; the rule applies when a password is next set. |
| REG-07 + NEW-03 | **Fixed (organization part).** An organization the student doesn't belong to is rejected instead of silently swapped; students in several organizations get a dropdown and the chosen one is kept. Test: `test_student_in_two_organizations_can_submit_for_the_second_one`. Draft re-confirmation is left for the request-form module (REQ-13 covers stale forms). |
| REG-01 | **Waiting on D4.** Self-registration for students, faculty and staff can't be opened until there is a way to verify the category. The current notice is accurate for the current rules. |
| REG-05 | **Waiting on D4.** |
| REG-06 | **Waiting on D6.** Needs the final privacy wording, and a database column to record when the user accepted it. |
| REG-08 | **Waiting on D7.** |
| NEW-04 | **Moved to 4.2** (part of removing signature uploads, APR-01). |

## 4.2 Approval workflow and digital approval

**D8, the real order of stages (from the code):**
1. Requestor submits.
2. Venue Custodian confirms venue availability (`venue_status`).
3. Supply Office initial availability review (`supply_office_review_status`); for urgent requests it also validates the supporting document.
4. In parallel: Equipment Custodian(s) endorse their items (`equipment_status`), skipped when no equipment; and for Outsiders the IGP records payment (`igp_payment_status`).
5. Supply Office final approval.

| ID | Status | Evidence | What is left |
|---|---|---|---|
| APR-01 | Confirmed | Five `/settings/signature` routes (`web.php:89,105,138,176,203`); submission requires the requestor's signature (`RequestorController.php:1485`); every approval snapshots the approver's signature image (`FacilityRequest::recordApprovalSignature`). | Remove uploads and the requirement; keep legacy files (APR-16). |
| APR-02 | Partly | Approvals already record the account (`approved_by_id`, history `user_id`, custodian checks through policies), but each approval is also bound to a signature image and the PDF prints images. | Make the account record the approval; drop the image binding. |
| APR-03 | Partly | `request_histories` records action, user and time for each step. No role-at-the-time, no document version, no protection against edits (plain Eloquent model). | Add role and version columns, block update/delete on the model. Needs a migration. |
| APR-04 | Partly | Server-side checks, row locks and transactions exist on every approval path. But notifications (`RequestStatusChanged`, not queued) are sent **inside** the transaction, before commit; a mail failure or slow mail server delays or can affect the approval. | Send notifications after commit. |
| APR-05 | Partly | Accepted proposals on a **pending** request reset all approvals (`RequestorController.php:353-368`). On an **approved** request the new schedule is applied and the old final approval is kept; custodians respond to the proposal, but the Supply Office does not re-approve. | Decide whether approved changes need Supply Office re-approval; version the record. |
| APR-06 | Not code | Legal statement and thesis limitation. | Waiting on D1. |
| APR-07 | Not code | Waiting on D1. | |
| APR-08 | Partly | A custodian who endorsed a request is already blocked from giving its final approval (`hasCustodianApprovalBy`, both final-approval paths). | Rule itself waits on D5; audit entries don't store the active role. |
| APR-09 | Confirmed | Stages documented above (D8 answered). Guide, progress bar and PDF still need to use them. | Belongs with DSH-01. |
| APR-10 | Partly | The policy checks the custodian's **current** assignment at the moment of action, and stale equipment endorsements from reassigned custodians stop counting. A venue endorsement given before reassignment stays valid. | Re-check venue endorsement on reassignment. |
| APR-11 | Done (mostly) | Every approval path locks the row and re-checks status inside the transaction; duplicates return "already recorded" or 409. | Add a MySQL race test (tests run on SQLite). |
| APR-12 | Confirmed | `recomputeEquipmentStatus()` treats an item with **no custodian as approved**, and one rejected item plus one approved item leaves the request "pending" with no clear next step. | Fix both. |
| APR-13 | Partly | Repeat clicks are handled by state checks, not idempotency keys. | Low risk with current checks. |
| APR-14 | Confirmed | No reminders, escalation or expiry for pending requests. | |
| APR-15 | Confirmed | No QR verification. Optional. | |
| APR-16 | Confirmed | Part of APR-01. | |
| APR-17 | Partly | Many workflow tests exist, but 34 tests already fail on `main` (approvals, inventory, notifications, reports). | Fix or update those tests while working on 4.2. |

### Found during the 4.2 audit

| ID | Severity | Problem |
|---|---|---|
| NEW-05 | **Critical** | The **Final Approval** button on the request details page (`requestor/show.blade.php:1080` → `RequestActionController::supplyFinalApproval`) never checks Outsider payment. The other path (`SupplyOfficeController`, line 1098) does. An unpaid Outsider request can be fully approved from the details page. It also doesn't refuse while a schedule proposal is pending. |

### 4.2 fixes (branch `fix/approval-module`)

| ID | Result |
|---|---|
| NEW-05 | **Fixed.** Final approval from the request details page now requires the IGP payment for Outsiders and refuses while a schedule proposal is pending; the page says why approval is disabled. Test: `test_unpaid_outsider_request_cannot_be_final_approved_from_request_details`. |
| APR-12 | **Fixed.** Requested equipment with no assigned custodian is no longer counted as approved; it keeps the equipment stage open, and the Supply Office sees "No custodian is assigned to: …" on the request. Equipment that comes with the venue is still covered by the venue confirmation. The "mixed" case is already defined in the code: any equipment rejection rejects the whole request (`custodianReject`). Test: `EquipmentCustodianCoverageTest`. |
