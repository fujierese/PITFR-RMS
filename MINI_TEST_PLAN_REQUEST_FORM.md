# Group Activity 2
## Mini Test Plan

**Date:** August 19, 2026  
**Module Under Test:** Request Form

### Group Members

| Name | Signature |
|---|---|
| ANDALES, NICK VINCENT D. | ____________________ |
| BARRO, DANIEL ZRAEL C. | ____________________ |
| BETOY, EPHRAIM D. | ____________________ |
| DANGEL, JENEVIEVE A. | ____________________ |
| NUÑEZ, ANA MAY M. | ____________________ |

## A. Test Overview

**Project Title:**  
Web-based Reservation and Scheduling System for School Venues and Equipment for Palompon Institute of Technology, Palompon, Leyte

**System Version / Module:**  
Current development version / Request Form module

**Purpose of Test:**  
To evaluate whether requestors can complete and submit a facility and equipment reservation request accurately, efficiently, and with minimal assistance. The test will also check whether required-field validation, venue and equipment availability feedback, document requirements, and the review summary support successful task completion.

**Test Date & Location:**  
August 19, 2026 / PIT computer laboratory or other quiet in-person testing area using the local web application

## B. Objectives

1. Determine whether target users can locate and understand the Request Form.
2. Verify that users can enter requestor information, activity details, date, time, participant count, venue, and equipment correctly.
3. Check whether the form clearly communicates invalid dates, incomplete required fields, venue conflicts, venue capacity limits, and equipment quantity limits.
4. Verify that role-based supporting-document requirements are understandable and that valid files can be uploaded.
5. Verify that users can review the live summary, upload an e-signature, and submit a complete request.
6. Identify usability problems, errors, and points where users need moderator assistance.

## C. Scope

### Features to be tested

- Opening and navigating the Request Form.
- Requestor information, college/department or organization fields, and requestor position.
- Activity name, purpose, expected number of participants, and request classification.
- Start date, end date, start time, end time, specific-time and whole-day reservation options.
- Venue selection, venue capacity warning, and schedule conflict notification.
- Equipment selection, available quantity display, and requested quantity entry.
- Live selected-items summary for activity, venue, date, time, and equipment.
- Urgent processing checkbox and justification field.
- Conditional supporting-document upload for student/faculty or external requestors.
- E-signature upload for all requestors.
- Submission checklist, validation messages, successful submission, and confirmation/control number.
- Draft autosave and restoration after accidental refresh, where available.

### Out of scope

- Custodian endorsement and administrator approval workflows.
- Notifications, email delivery, and real-time broadcasting after submission.
- Payment processing and external rental-fee collection.
- Equipment release, return, and post-event inventory workflows.
- Account registration, login, password recovery, and profile management.
- Performance, security, accessibility compliance, and compatibility testing across all browsers.
- Large-scale load testing and simultaneous multi-user booking beyond a basic conflict check.

## D. Test Methodology

**Type of Testing:** Formative usability testing with functional checks

**Test Environment:**

- Web browser: Google Chrome or Microsoft Edge, current available version.
- Application: Local PIT Facility Request Portal running at `http://127.0.0.1:8000`.
- Device: Desktop or laptop with keyboard, mouse, and stable local network connection.
- Test data: Test accounts for student/faculty and external requestors, available venues, available equipment, one known venue conflict, and sample valid/invalid documents.
- Sample documents: PDF proposal or IGP receipt, PNG/JPG e-signature, and an unsupported or oversized file for validation testing.

**Test Approach:** Moderated, in-person, task-based testing. Each participant will complete the assigned scenarios while the moderator observes, records time and errors, and asks brief follow-up questions after each task. The moderator may clarify the task but will not lead the participant through the interface.

## E. Participants

**Target Users:**

- Students or faculty/staff who submit school facility and equipment requests.
- External requestors or organization representatives, if an external test account is available.

**Number of Participants:** 5 participants, preferably one participant per group member. At least three participants should represent internal requestors; one external-requestor session should be included when possible.

## F. Test Scenarios and Tasks

| Scenario | Task | Success Criteria |
|---|---|---|
| **Scenario 1: Complete basic request details** | Open the Request Form and enter a realistic activity name, purpose, expected participants, requestor position, and valid future start/end dates and times. Choose the appropriate reservation duration. | Participant identifies and completes the required fields without moderator input; entered values remain visible and the form does not show avoidable validation errors. |
| **Scenario 2: Select an available venue and equipment** | Select a venue that can accommodate the expected participants. Select two equipment items and enter quantities within the displayed availability. Review the selected-items summary. | Participant selects the correct venue and quantities; availability information is understood; summary reflects the activity, venue, dates, times, and equipment accurately. |
| **Scenario 3: Respond to invalid or conflicting choices** | Try one invalid condition, such as a past date, end time before start time, participant count above venue capacity, unavailable equipment quantity, or a known venue schedule conflict. Correct the problem. | Form provides a visible and understandable warning or validation message; participant can identify the cause and correct the input without losing previously entered data. |
| **Scenario 4: Upload required documents** | Upload the appropriate supporting document for the account type and upload a valid PNG/JPG e-signature. Attempt one invalid file type or missing required document, then correct it. | Correct document fields are shown for the user type; valid files show their names or previews; invalid or missing files are rejected with a clear message; participant can recover and continue. |
| **Scenario 5: Review and submit the request** | Review the submission checklist and live summary, optionally request urgent processing with a reason, then submit the completed request. Record the confirmation and control number. | Participant confirms the information before submission, completes all required checklist conditions, submits once, sees a success response, and can identify the resulting control number/status. |

### Moderator Notes

- Record the participant's start and finish time for each scenario.
- Record direct errors, repeated attempts, moderator assistance, hesitation, and comments.
- Do not use real personal data or real reservation dates that could affect operations.
- Reset or remove each test request after the session according to the project team's test-data procedure.

## G. Success Criteria

The following benchmarks define success for the formative test:

**Effectiveness:**

- At least 4 of 5 participants complete Scenarios 1, 2, 4, and 5 successfully.
- At least 4 of 5 participants correctly identify and recover from the invalid/conflicting condition in Scenario 3.
- At least 90% of required task steps are completed correctly across all participants.
- No critical error causes data loss, an incorrect reservation, or an unintended submission.

**Efficiency:**

- At least 4 of 5 participants complete the basic request flow in 15 minutes or less, excluding moderator briefing and intentional invalid-input checks.
- Participants require no more than one moderator prompt per scenario on average.
- The form preserves previously entered data after validation errors or correction attempts.

**Satisfaction:**

- At least 4 of 5 participants rate the form at least 4 out of 5 for ease of use.
- At least 4 of 5 participants rate the instructions, validation feedback, and review summary as clear.
- No repeated high-severity complaint is left without an action recommendation.

## H. Metrics and Actual Results

Complete this section during or immediately after testing. Do not replace planned benchmarks with estimates.

### Effectiveness

| Metric | Target | Actual Result | Status |
|---|---:|---:|---|
| Participants completing the basic request flow | 4/5 or higher | ____ / 5 | ____ |
| Participants recovering from an invalid/conflicting input | 4/5 or higher | ____ / 5 | ____ |
| Correct task steps completed | 90% or higher | ____% | ____ |
| Critical errors causing data loss or unintended submission | 0 | ____ | ____ |

### Efficiency

| Metric | Target | Actual Result | Status |
|---|---:|---:|---|
| Median completion time for the basic flow | 15 minutes or less | ____ minutes | ____ |
| Average moderator prompts per scenario | 1 or fewer | ____ | ____ |
| Sessions with lost data after an error or correction | 0 | ____ | ____ |

### Satisfaction

| Metric | Target | Actual Result | Status |
|---|---:|---:|---|
| Participants rating ease of use 4 or 5 out of 5 | 4/5 or higher | ____ / 5 | ____ |
| Participants rating instructions and feedback clear | 4/5 or higher | ____ / 5 | ____ |
| Repeated high-severity complaints | 0 unresolved | ____ | ____ |

Use a short 1-to-5 post-test questionnaire:

1. The Request Form was easy to understand.
2. I could find the information and controls I needed.
3. The validation and availability messages were clear.
4. The review summary helped me verify my request.
5. I would use this form again for a facility/equipment request.

## I. Reporting Plan

The moderator will consolidate the results after all sessions and prepare:

- A participant-by-scenario completion table showing pass, assisted pass, or fail.
- A bar chart comparing completion rates with the effectiveness target.
- A table or bar chart of average and median completion times.
- A summary of error counts by category: required fields, schedule, capacity, equipment, documents, and submission.
- A satisfaction chart showing the average score for each questionnaire item.
- A prioritized recommendation list containing the issue, evidence, severity, proposed improvement, and responsible person.

### Recommendation Priority

- **Critical:** Prevents submission, causes data loss, creates a wrong reservation, or exposes sensitive data.
- **High:** Prevents several target users from completing a key task or produces misleading availability/validation feedback.
- **Medium:** Causes repeated hesitation, extra steps, or recoverable errors.
- **Low:** Cosmetic or wording issue with little effect on task completion.

### Final Report Conclusion Template

**Overall result:** ____ of 5 participants completed the full request flow.  
**Main strengths:** __________________________________________________________  
**Main usability problems:** _________________________________________________  
**Recommended actions before the next test:** _________________________________  
**Retest date:** ____________________
