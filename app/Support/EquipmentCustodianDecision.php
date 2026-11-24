<?php

namespace App\Support;

/**
 * Equipment custodian decisions (Additional Enhancements, part 1). An equipment
 * custodian decides only about the equipment assigned to them: Endorse, Decline (with
 * a reason) or Report an Issue. None of these changes the reservation's own status;
 * the Supply Office keeps the reservation-level decisions.
 */
final class EquipmentCustodianDecision
{
    /** Reasons offered when declining assigned equipment. */
    public const DECLINE_REASONS = [
        'damaged' => 'Equipment is damaged or defective',
        'maintenance' => 'Equipment is under maintenance',
        'incomplete' => 'Equipment is incomplete or missing essential parts',
        'quantity' => 'Requested quantity cannot be provided',
        'unsafe' => 'Equipment is unsafe or unsuitable for use',
        'other' => 'Unavailable for another operational reason',
    ];

    /** Issue types for an equipment issue report on a request. */
    public const ISSUE_TYPES = [
        'damaged' => 'Damaged or defective',
        'maintenance' => 'Needs maintenance',
        'incomplete' => 'Missing parts or incomplete',
        'unsafe' => 'Unsafe to use',
        'other' => 'Other concern',
    ];

    public static function declineReasonLabel(?string $key): string
    {
        return self::DECLINE_REASONS[$key] ?? 'Other reason';
    }

    public static function issueTypeLabel(?string $key): string
    {
        return self::ISSUE_TYPES[$key] ?? 'Other concern';
    }
}
