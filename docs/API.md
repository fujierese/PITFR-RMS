# API access and role behavior

Requests use JSON and the `/api` prefix. Authenticate with a bearer token from `POST /api/login`. Tokens expire after 24 hours; `POST /api/logout` revokes the current token. Inactive accounts are denied by API middleware and inactive-account tokens are revoked when access is attempted.

The request collection endpoint is paginated and owner-scoped for requestors. Custodians see requests that mention their assigned venue or equipment, with a restricted field set; equipment quantities are limited to the equipment assigned to that custodian. Administrators can list all requests.

## Facility-request actions

| Action | Expected authority | Notes |
|---|---|---|
| Create | Requestor or administrator policy | Requires future dates, increasing start/end datetimes, active catalog equipment, and positive per-item quantities. Urgent submissions within 48 hours are flagged for human Supply Office review; they do not silently bypass final conflict review. |
| Read one | Request owner, administrator, or custodian assigned to a requested resource | The policy is checked per request. |
| Update | Authorized owner/administrator | Not implemented through this API; returns `501` without a success-shaped response. Use the web edit/reschedule flow. |
| Delete/cancel | Request owner/administrator policy | Only pending requests can be cancelled. Approved requests cannot be cancelled through this endpoint. |
| Approve/reject | Custodian assigned to the corresponding stage/resource | Venue and equipment endorsements remain separate from final Supply Office approval. |
| Return equipment | Assigned equipment custodian | Return, damage, and missing counts are validated against the request's item allocations by the model; only newly returned usable units restore stock. |

The public `GET /api/reservations` response is a reduced calendar DTO and intentionally omits requestor contact details, internal approval statuses, and request links. API availability checks require an authenticated active account. Validation failures return `422`; unauthorized and unauthenticated requests return `403` and `401`, respectively. State conflicts may return `409`.

Use `php artisan route:list --path=api` to inspect the current route declarations. This document describes the implemented API subset, not a versioned compatibility guarantee.
