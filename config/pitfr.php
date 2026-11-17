<?php

return [
    'final_approver_username' => env('PITFR_FINAL_APPROVER_USERNAME', 'asala@gmail.com'),
    // APR-14: review service levels for pending requests (hours a stage may wait).
    'review_sla' => [
        'reminder_after_hours' => (int) env('PITFR_REVIEW_REMINDER_HOURS', 48),
        'escalate_after_hours' => (int) env('PITFR_REVIEW_ESCALATE_HOURS', 96),
        'expire_when_start_passes' => (bool) env('PITFR_EXPIRE_UNAPPROVED_REQUESTS', true),
    ],
    'contact' => [
        'office' => 'Supply Office (Near PIT School Clinic)',
        'email' => 'pitsupplyoffice@gmail.com',
        'phone' => 'No contact number available',
        'address' => 'Evangelista St. Guiwan II, Palompon, Leyte, Philippines 6538',
    ],
];