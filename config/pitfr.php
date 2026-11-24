<?php

return [
    'final_approver_username' => env('PITFR_FINAL_APPROVER_USERNAME', 'asala@gmail.com'),
    // D4: email domains that verify a student, faculty or staff account automatically.
    'institutional_email_domains' => env('PITFR_INSTITUTIONAL_EMAIL_DOMAINS', 'pit.edu.ph'),
    // APR-14: review service levels for pending requests (hours a stage may wait).
    'review_sla' => [
        'reminder_after_hours' => (int) env('PITFR_REVIEW_REMINDER_HOURS', 48),
        'escalate_after_hours' => (int) env('PITFR_REVIEW_ESCALATE_HOURS', 96),
        'expire_when_start_passes' => (bool) env('PITFR_EXPIRE_UNAPPROVED_REQUESTS', true),
    ],
    // Additional Part 2: Activity Proposal deadlines and reminders (working days; weekends skipped,
    // no holiday calendar). The 3-working-day default is a proposed standard, not fixed.
    'activity_proposal' => [
        'default_working_days_before' => (int) env('PITFR_PROPOSAL_DEADLINE_WORKING_DAYS', 3),
        'reminder_working_days_before' => [3, 1],
        'overdue_repeat_working_days' => (int) env('PITFR_PROPOSAL_OVERDUE_REPEAT_DAYS', 3),
    ],
    'contact' => [
        'office' => 'Supply Office (Near PIT School Clinic)',
        'email' => 'pitsupplyoffice@gmail.com',
        'phone' => 'No contact number available',
        'address' => 'Evangelista St. Guiwan II, Palompon, Leyte, Philippines 6538',
    ],
];