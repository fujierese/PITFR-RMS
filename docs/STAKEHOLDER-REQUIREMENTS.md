# PITFR Stakeholder Requirements

**Status:** Stakeholder decisions implemented; automated test suite passing
**Source:** Supply Office and requestor clarifications provided on 2026-10-23

## 1. Purpose and scope

This document converts stakeholder feedback into functional requirements and acceptance criteria for the PITFR Reservation Management System. It covers request submission, venue availability, Supply Office and custodian review, changes and cancellations, equipment, payment coordination, priorities, and attribution.

Outsiders may use their existing PITFR accounts to submit and track their own requests. The IGP is an internal role that receives payment, records the official receipt number, and verifies the proposed schedule with the Supply Office. Outsiders cannot reserve the Gym; PIT users may request it for permitted events.

## 2. Roles

| Role | Responsibilities |
|---|---|
| Requestor | Sets up an e-signature, checks venue availability, submits and tracks requests, and is informed of proposed changes. |
| Supply Office | Performs the initial availability review, coordinates changes and cancellations, resolves custodian rejections, and gives final confirmation or approval. |
| Custodian | Reviews requests for assigned venues or equipment and responds to approval or change requests. A person may also hold other roles. |
| IGP | Receives outsider payments, records the official receipt number, and coordinates schedule availability confirmation with the Supply Office. Handles refunds under the manual process. |
| Supply Office staff | Jose Julius J. Paloma, Ederson C. Petallo, Argel C. Nuñez, and Arlene L. Sala. Each staff member must be individually identifiable when acting. One person may hold multiple roles (for example, both Custodian and Supply Office); the same account carries those role assignments. |

## 3. Reservation workflow

### 3.1 Account setup and request submission

1. A person without an account requests one from the Supply Office, sets a password, and logs in.
2. On first use, an account holder sets up an e-signature.
3. Before submitting, the requestor can check whether the selected venue and date/time are available.
4. The requestor selects one or more venues for use in the same request and supplies the reservation dates, duration, and other request details. Each selected venue is a requested booking, not an alternative from which the system silently chooses one.
5. Every request, including urgent requests and requests that appear available on the calendar, receives an initial Supply Office availability review. Calendar availability is advisory, not confirmation.
6. The Supply Office checks every selected venue and schedule. If all are available, the request proceeds to the relevant custodian(s) for approval and signature.
7. If a selected venue is unavailable, the Supply Office may directly assign an available alternative venue or reschedule. It informs the requestor and affected custodians of the change, and relevant custodians check the proposed booking. The Supply Office records the decision.
8. After custodian responses, the request returns to the Supply Office for final review. A custodian rejection returns to the Supply Office, which may directly assign an available venue or rescheduling option and informs the requestor and affected custodians. Rejection alone does not silently transfer or close the request.
9. A reservation is approved only after the initial Supply Office availability review, required custodian approvals, and Supply Office final approval.

### 3.2 Outsider reservation coordination

1. An outsider may submit their request through their own PITFR account. The IGP may assist with coordination.
2. Every request goes through initial Supply Office availability review before the IGP receives payment.
3. Once the Supply Office confirms the requested schedule is available, the IGP receives payment and records the official receipt number and Supply Office availability confirmation.
4. The reservation continues through applicable custodian review and Supply Office final approval. Initial availability confirmation is not final reservation approval.
5. An unavailable request is not treated as confirmed for payment; the Supply Office proposes another available schedule or venue and informs the requestor and IGP.
6. Outsider requests cannot use the Gym. PIT users may submit requests for permitted Gym events, including proms, small orientations, small seminars, pickleball, and badminton.

### 3.3 Proposed changes to an approved reservation

1. Only the Supply Office may initiate an edit, reschedule, or venue transfer after approval.
2. The Supply Office records the proposed change and identifies affected users, including the requestor and affected custodians.
3. Notify the requestor and all affected custodians/users of the proposed change and record their responses. For ordinary changes, obtain acceptance from all affected people before applying the change. For a memorandum-priority transfer, the requestor is notified but their consent is not required.
4. The approved reservation remains unchanged while the proposal is awaiting responses. No response is not consent.
5. Apply an ordinary proposed change only after all affected people accept it, the target venue and schedule are available, and relevant custodians have confirmed. A memorandum-priority transfer may proceed without requestor consent after notice, availability confirmation, and applicable custodian checks.
6. If the target venue is unavailable or someone rejects an ordinary proposal, do not apply that proposal. The Supply Office finds another available option and informs affected people; the existing approved reservation remains in effect unless separately canceled.
7. For ordinary proposals, set a response deadline when the proposal is sent. If anyone has not responded by the deadline, expire the proposal without changing the booking and notify everyone. A memorandum-priority transfer does not wait for requestor consent or response; affected custodians are informed and applicable custodian checks are completed.
8. Before making any approved reservation change, the Supply Office informs the requestor and every affected custodian. For a memorandum-priority transfer, the notice must state the destination venue and reason, for example: “Your reservation will be transferred to [venue name] because of a memorandum from [office].” The requestor is informed but cannot veto this priority transfer. Do not silently change an approved booking.

### 3.4 Cancellation

1. An approved request can be canceled; cancellation is not restricted to pending requests.
2. The Supply Office contacts the requestor to confirm the cancellation and informs affected users, including custodians.
3. A cancellation must be requested no later than the calendar day before the reservation date. Cancellation is allowed through that day even if fewer than 24 hours remain; requests made on the reservation date are outside the normal cancellation window.
4. Record the cancellation, the acting staff member, confirmation, timestamp, and notifications in the request history.

## 4. Functional requirements and acceptance criteria

### P0 — Workflow integrity

**FR-01 — Supply Office availability review**

- A submitted request enters a Supply Office availability review before custodian approval begins.
- The Supply Office can record an available/unavailable decision.
- An unavailable decision allows the Supply Office to suggest another date/time or venue and to request a custodian check of a proposed alternative.
- A request cannot become finally approved until required custodian decisions and Supply Office final approval are recorded.
- The same person cannot perform both a custodian approval and the final Supply Office approval for the same request. If staffing makes separation impractical, require a second Supply Office staff member to review and document the exception.

**Acceptance:** Every request, urgent or otherwise, follows the order Supply Office availability review → custodian review → Supply Office final review. An available calendar slot does not skip the initial Supply Office review. An unavailable first review does not start the ordinary approval/signature stage. One person cannot perform both the custodian and final Supply Office approval; any staffing exception requires a second Supply Office reviewer and a recorded justification.

**FR-02 — Custodian rejection handling**

- A custodian rejection returns the request to the Supply Office for resolution.
- The Supply Office can directly assign an available alternative venue or rescheduling option, inform the requestor and affected custodians, or reject the request when no available option exists.
- Urgent requests follow the same initial Supply Office availability review as all other requests.

**Acceptance:** A custodian rejection is visible to the Supply Office and does not automatically approve or close the request. The Supply Office may directly assign an available alternative and informs the requestor and affected custodians.

**FR-03 — Approved request change proposal**

- Only Supply Office staff may initiate edits, rescheduling, or venue transfers on an approved reservation.
- Proposals identify all affected users and custodians and record each response.
- For an ordinary proposal, do not change the approved reservation until all affected people accept, relevant custodians confirm, and the new schedule is available. A memorandum-priority transfer does not require requestor consent, but the requestor and affected custodians must be informed, the alternative must be available, and applicable custodian checks must be completed.
- For ordinary proposals, set a response deadline. A proposal with outstanding responses expires at the deadline; retain the original booking and notify everyone. A memorandum-priority transfer does not wait for requestor consent or response.
- Before applying any change, inform the requestor and every affected custodian. A memorandum-priority transfer notice names the destination venue and explains that the transfer is because of a memorandum.
- If an ordinary proposal cannot proceed, preserve the existing approved reservation and record why. For a memorandum-priority transfer, the Supply Office assigns another available alternative rather than allowing the requestor to veto the transfer.

**Acceptance:** Requestors and custodians cannot directly modify an approved reservation. While an ordinary proposal is pending, the original approved details remain active. A memorandum-priority transfer may proceed without requestor consent after notice, availability confirmation, and applicable custodian checks.

**FR-04 — Cancellation of approved requests**

- Support cancellation of approved as well as pending requests.
- For an approved request, the Supply Office confirms the requestor's intent and notifies affected users before completing the cancellation.
- Enforce a cutoff at the end of the calendar day before the reservation date, even if fewer than 24 hours remain; do not accept ordinary cancellation requests on the reservation date.
- Preserve a cancellation history and notification outcomes.

**Acceptance:** An approved request can be canceled through the Supply Office workflow through the day before its reservation date, even if fewer than 24 hours remain. A request made on the reservation date is blocked with a clear explanation.

### P1 — Request creation and scheduling

**FR-05 — Availability while selecting dates**

- Show availability for the selected venue and date/time during request creation.
- Refresh the result when the venue, date, or time changes.
- Recheck availability at Supply Office review and before final approval; a form-time result is advisory and does not reserve the slot.
- Show conflicts in a way that lets the requestor choose another available date or time.

**Acceptance:** A requestor can distinguish available from unavailable venue/date/time choices before submission; a concurrent booking is still caught by the server-side review.

**FR-06 — Multiple venue choices and conditional schedule fields**

- Allow more than one venue to be selected in a request.
- Multiple selected venues are all intended for use in that same reservation request; they are not mutually exclusive alternatives.
- Show the start date, end date, reservation duration/time, and whole-day option only after at least one venue is selected.
- Display shared schedule fields once, not duplicated for each selected venue.
- Check conflicts for each venue considered by the request.

**Acceptance:** With no venue selected, schedule fields are hidden. With one or more venues selected, one shared schedule is displayed and availability is checked for each selected venue.

**FR-07 — Reservation hours**

- Whole-day reservations use the regular reservation window of 8:00 AM through 5:00 PM.
- Specific-time reservations capture exact start and end times to match submitted documents.
- Block reservation times from 12:00 AM through 4:59 AM.
- Specific-time reservations may be made after 5:00 PM through 11:59 PM. No reservations may overlap 12:00 AM–4:59 AM.
- Apply the same time validation to the user interface, web submission, API submission, and approval paths.

**Acceptance:** A whole-day reservation is represented as 8:00 AM–5:00 PM. A specific-time reservation retains its exact selected times, including allowed times after 5:00 PM through 11:59 PM. A request overlapping 12:00 AM–4:59 AM is rejected.

**FR-08 — Urgent requests**

- Allow an urgent designation only when the request is submitted on the same local calendar date as the entire reservation; the reservation must start and end on that date.
- Continue to require Supply Office availability and final review.
- Require the submitter to upload a proposal or memorandum order; the Supply Office validates it before approval.
- A memorandum from CHED, the Office of the President (OP), or the Office of the Vice President (OVP) can require an existing student-organization reservation to be transferred, notwithstanding student-organization priority. The Supply Office coordinates an available alternative and informs affected users.

**Acceptance:** An urgent request cannot be submitted for a different reservation date or span multiple dates. The required proposal or memorandum order is available for Supply Office validation before approval. Urgent status does not bypass initial availability review.

**FR-09 — Request list and past reservations**

- Do not show past reservations in the ordinary request list.
- Treat a reservation as past only when its end date is earlier than the current local date, so a multi-day reservation that is still active is not hidden.
- Keep past reservations on a separate history page accessible to all users, while displaying only records/fields appropriate to the viewer's authorization.

**Acceptance:** On October 23, a reservation ending October 22 or earlier is omitted from the ordinary list and appears on the history page; a reservation ending October 23 or later remains in the ordinary list.

### P1 — Equipment, priorities, and payment coordination

**FR-10 — “Others” is equipment-only**

- Remove “Others” as a general request category.
- Allow “Others” only in the equipment selection flow, with a required description so the requested item can be reviewed and assigned.

**Acceptance:** Users cannot select “Others” as a venue or general-purpose request type; equipment “Other” requests identify the requested item.

**FR-11 — Equipment ownership and routing**

- Lights: route to Sir Rogelio Guillemer for setup when selected.
- Podium and HDMI: route to the custodian assigned to CHIC.
- Television: CHIC has a working TV; the Balay Alumni TV is broken and unavailable until its status is changed.
- Record the responsible custodian/handler for each item rather than relying only on venue ownership.

**Acceptance:** Selecting lights routes setup to Sir Rogelio Guillemer; selecting a podium or HDMI routes approval to the CHIC-assigned custodian. The CHIC TV can be requested, while the Balay Alumni TV cannot be allocated.

**FR-12 — Microphone inventory**

- Maintain separate inventory counts of three wireless microphones and three non-wireless microphones.
- Validate requested quantities against available inventory and prevent allocations beyond stock.

**Acceptance:** The initial available stock is three for each microphone type; requests cannot allocate more usable units than are available.

**FR-13 — Reservation priorities**

- Student organizations have priority in reservation conflicts.
- If CHED, OP, or OVP supplies a memorandum, the Supply Office may require a student-organization reservation to be transferred to an available venue or schedule.
- Record the reason and acting staff member when the Supply Office changes an existing schedule to resolve a priority conflict.

**Acceptance:** The Supply Office can identify student-organization priority and validate a memorandum from CHED, OP, or OVP as the basis for a proposed transfer. The transfer follows the approved-change process and is attributed and auditable.

**FR-14 — IGP role and outsider payment status**

- Add an IGP role/account with access limited to payment receipt/refund records and outsider-reservation coordination.
- Allow outsiders to submit requests using their own accounts.
- Provide a status that distinguishes availability awaiting review, availability confirmed, payment pending/confirmed, and reservation approval.
- Record the official receipt number and the Supply Office confirmation that the proposed schedule is available.
- The IGP receives payments and handles refunds under the manual process; the Supply Office supplies schedule availability confirmation.
- The IGP must wait for Supply Office availability confirmation before receiving payment.
- Block outsider Gym requests while allowing PIT-user Gym requests for permitted events.

**Acceptance:** An outsider can submit a request from their account. The IGP can record the receipt number only after Supply Office availability confirmation and cannot perform Supply Office approval actions unless separately authorized.

**FR-15 — Fees and refunds**

- The Gym is the only venue currently identified as fee-bearing.
- Do not offer the Gym for outsider use because it is considered unsafe for outsiders.
- PIT users may request the Gym for school activities, including proms, small orientations, small seminars, pickleball, and badminton.
- The IGP handles refunds under the manual process; record relevant status and receipt/reference details without inventing refund eligibility or amounts.

**Acceptance:** A PIT user can request the Gym for a school activity, while an outsider cannot reserve it. IGP refund handling is recorded without automating unconfirmed refund rules.

### P2 — Identity, display, and usability

**FR-16 — Individual Supply Office attribution**

- Give the four named Supply Office staff individually identifiable accounts with equivalent permissions and a shared Supply Office dashboard, rather than relying on an unattributed shared login. A staff member with multiple roles (for example, Supply Office and CHIC custodian) has one account with each assigned role. Determine authorization from assigned permissions without requiring an explicit role switcher; attribute actions to that individual and the role used.
- Attribute every relevant action to the acting person, including availability confirmation, approval, rejection, cancellation, and change proposals.
- Show the actor in audit history, request details visible to authorized users, and the calendar tooltip.
- Do not expose requestor private details in public calendar data.

**Acceptance:** Each action can be traced to one of the four named staff members and the role used; authorized users can see the responsible person in request history and calendar details.

**FR-17 — Venue calendar colors**

| Venue | Color |
|---|---|
| CHIC | Red |
| Balay Alumni | Blue |
| Gym | Brown |
| Covered Courts | Green |
| Oval | Green |

**Acceptance:** The same venue color is used consistently in calendar events and legends.

**FR-18 — Reservation history**

- Provide a separate history page for past reservations.
- Allow signed-in users to access the history page, but apply role-appropriate field visibility and request-detail authorization.
- Keep records specified by status and relevant reservation details rather than removing past reservations.

**Acceptance:** Past reservations are discoverable on the history page, and a user cannot obtain private request details solely because the history listing is broadly visible.

## 5. Cross-cutting requirements

- Enforce role permissions and business rules on the server; hiding a control in the interface is not authorization.
- Record a timestamp and actor for review decisions, confirmations, approvals, rejections, schedule changes, cancellations, and payment-status changes.
- Notify affected users when an approved change is proposed, accepted, rejected, or applied, and when an approved reservation is canceled.
- Before an approved reservation is changed, notify the requestor and every affected custodian. A memorandum-priority transfer notification states the destination venue and the memorandum reason.
- Preserve request and decision history rather than overwriting the only record of prior details.
- Apply local institutional time consistently for date cutoffs, availability, urgent eligibility, and calendar display.

## 6. Confirmed implementation decisions

The following policy points have been confirmed for implementation:

1. **Cancellation cutoff:** Allow cancellation through the previous local calendar day, even if fewer than 24 hours remain. Ordinary same-day cancellations are not allowed.
2. **Change response deadline:** Set a response deadline for affected users and custodians. An unaccepted proposal expires at the deadline; the original booking remains unchanged and everyone is notified.
3. **Change notifications:** Before a change, the Supply Office informs the requestor and every affected custodian. A memorandum-priority transfer notice names the destination venue and explains that the transfer is because of a memorandum. Approved bookings must not be changed silently.
4. **IGP payment sequence:** The IGP waits for the Supply Office to confirm schedule availability before receiving payment.
5. **Alternative venue assignment:** The Supply Office may directly assign an available alternative. The requestor and affected custodians are informed before the change; relevant custodians check the alternative booking.
6. **Memorandum-priority transfer:** The requestor cannot veto a transfer required by a CHED, OP, or OVP memorandum. The Supply Office informs affected users and custodians, names the available destination venue and memorandum reason, and performs the transfer with applicable custodian checks.
7. **History access:** Signed-in users can access the history page; role-appropriate privacy protections still apply.

The following implementation decisions have also been agreed:

1. **Supply Office accounts:** Give each staff member an individually identifiable account, assign all of their roles to that account, and present a shared Supply Office dashboard. Determine access by permissions; an explicit role switcher is not required.
2. **Multiple-role approval separation:** A person who approves a request as its custodian cannot also perform its final Supply Office approval. If staffing makes this impractical, require a second Supply Office staff member to review and document the exception.

## 7. Suggested implementation order

1. Workflow transitions, availability rechecks, actor audit trail, approved change proposals, cancellation, and notification rules.
2. Request-form venue/time selection, availability feedback, regular hours, urgent memorandum validation, and request-list/history behavior.
3. Equipment catalog/routing, microphone quantities, and venue colors.
4. IGP coordination, outsider account access, payment receipt numbers, and manual refund records.
5. Implement individual Supply Office accounts with shared dashboard access, role-based permissions, and separation of custodian and final Supply Office approvals.
