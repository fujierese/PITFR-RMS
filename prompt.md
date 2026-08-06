I want you to continue developing my Laravel project (PITFR - Web-Based Reservation and Scheduling System).

IMPORTANT:

- Do NOT rewrite the entire project.
- Do NOT change working functionality.
- Do NOT remove existing features.
- Maintain compatibility with the current database and workflow.
- Follow Laravel best practices.
- Implement ONE feature at a time.
- After completing each feature, stop and wait for my approval before proceeding to the next one.
- Preserve existing UI design unless necessary.
- Keep backward compatibility with existing records.

========================
PHASE 2 - WORKFLOW IMPROVEMENTS
========================

1. Separate the Supply Office sidebar into dedicated pages:

- Pending Requests
- Final Approval Queue
- Approved Requests
- Rejected Requests
- Needs Reschedule
- Equipment Returns

Each page must:
- have its own route
- controller method
- Blade view
- pagination
- search
- filters
- proper authorization

Do not duplicate code unnecessarily.

----------------------------

2. Final Approval Queue

Display only requests waiting for final approval.

Actions:
- View
- Approve
- Reject
- Needs Reschedule

Do not display already approved requests here.

----------------------------

3. Approved Requests

Create a dedicated page showing only approved reservations.

Features:

- search
- pagination
- filter by venue
- filter by date
- filter by department
- view details
- print
- export

----------------------------

4. Equipment Returns

Separate page.

Group requests into:

- Pending Return
- Partial Return
- Returned
- Overdue

Maintain existing return workflow.

========================
PHASE 3 - SMART FEATURES
========================

5. Reservation Reminder Notifications

Implement automatic notifications for requestors:

- 2 days before reservation
- 1 day before reservation
- reservation day

Use Laravel Scheduler and Notifications.

Do not hardcode dates.

----------------------------

6. Reservation Revision

Allow Admin/Supply Office to revise:

- venue
- schedule
- time

When revised:

- notify requestor
- store previous values
- store revision reason
- store revised_by
- store revised_at

Do not overwrite history.

----------------------------

7. Revision History

Add a timeline showing every revision.

Display:

Original

↓

Revision

↓

Latest

Include:

- date
- user
- reason
- changed fields

========================
PHASE 4 - UI/UX
========================

8. Mobile Responsive

Audit every page.

Improve:

- tables
- sidebar
- forms
- dashboard
- calendar
- request pages
- approval pages
- notifications

Maintain desktop layout.

----------------------------

9. Guest Page

Improve landing page:

- enlarge logo
- improve spacing
- keep branding
- responsive

----------------------------

10. Better Tables

Implement:

- responsive tables
- sticky header
- search
- filters
- pagination

========================
PHASE 5 - POLISH
========================

11. Notification Center

Improve notifications.

Show:

- title
- description
- date
- status

Allow:

- mark as read
- mark all as read

----------------------------

12. Reports

Improve reports.

Support:

- monthly
- yearly
- venue usage
- equipment usage
- department usage

----------------------------

13. Export

Support:

- PDF
- Excel
- CSV

Do not break existing exports.

========================
IMPLEMENTATION RULES
========================

For every feature:

1.
Inspect the current implementation.

2.
Explain what files will be modified.

3.
Explain why.

4.
Implement only that feature.

5.
Verify:

- routes
- controllers
- views
- models
- middleware
- validation
- notifications
- database

6.
Run tests if applicable.

7.
Provide a summary of modified files.

8.
STOP.

Wait for my approval before implementing the next feature.

Do NOT continue automatically.

If any database migration is required:

- explain why
- generate the migration only
- do not modify unrelated tables

Maintain existing coding style.

Do not introduce breaking changes.

Always preserve backward compatibility.