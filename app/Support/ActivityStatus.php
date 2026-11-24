<?php

namespace App\Support;

/**
 * Additional Enhancements, Activity History: the icon and colour for each history
 * entry, chosen from what the entry records. Display only; history records are not
 * changed.
 *
 * Approved = green check-circle, Needs Revision = yellow edit, Rejected = red
 * x-circle, Pending = orange clock, Cancelled = gray ban. Other entries get a
 * fitting icon: submitted (blue send), issue or conflict (amber alert), edited
 * (blue pencil), information (slate info).
 */
final class ActivityStatus
{
    public const TONES = [
        'approved' => 'bg-emerald-500 text-white',
        'revision' => 'bg-yellow-400 text-yellow-950',
        'rejected' => 'bg-rose-600 text-white',
        'pending' => 'bg-orange-500 text-white',
        'cancelled' => 'bg-slate-400 text-white',
        'submitted' => 'bg-sky-600 text-white',
        'warning' => 'bg-amber-500 text-white',
        'edited' => 'bg-indigo-500 text-white',
        'info' => 'bg-slate-500 text-white',
    ];

    public const LABELS = [
        'approved' => 'Approved',
        'revision' => 'Needs revision',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
        'submitted' => 'Submitted',
        'warning' => 'Attention',
        'edited' => 'Edited',
        'info' => 'Update',
    ];

    /** SVG path data (24x24, stroke icons). */
    public const ICONS = [
        'approved' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9.5"/>',
        'revision' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'rejected' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        'pending' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'cancelled' => '<circle cx="12" cy="12" r="9"/><path d="m5.7 5.7 12.6 12.6"/>',
        'submitted' => '<path d="M21 3 10 14"/><path d="M21 3 14 21l-4-7-7-4 18-7Z"/>',
        'warning' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4m0 3v.01"/>',
        'edited' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8v.01"/>',
    ];

    public static function forAction(string $action, ?bool $returnCompleted = null): string
    {
        $a = strtolower($action);

        if ($a === 'equipment_returned') {
            return $returnCompleted ? 'approved' : 'pending';
        }

        return match (true) {
            $a === 'cancellation_requested' => 'pending',
            str_contains($a, 'cancel') => 'cancelled',
            str_contains($a, 'expired') => 'cancelled',
            str_contains($a, 'issue') || str_contains($a, 'conflict') || str_contains($a, 'escalat') => 'warning',
            str_contains($a, 'not_endorsed') || str_contains($a, 'rejected') || str_contains($a, 'declined') => 'rejected',
            str_contains($a, 'unavailable') || str_contains($a, 'revision_requested') || str_contains($a, 'needs_') || str_contains($a, 'change_request') || $a === 'change_requested' => 'revision',
            str_contains($a, 'approved') || str_contains($a, 'endorsed') || str_contains($a, 'confirmed') || str_contains($a, 'accepted') || str_contains($a, 'completed') => 'approved',
            str_contains($a, 'edited') => 'edited',
            str_contains($a, 'submitted') => 'submitted',
            str_contains($a, 'pending') || str_contains($a, 'review') || str_contains($a, 'proposed') || str_contains($a, 'requested') || str_contains($a, 'forwarded') || str_contains($a, 'reminder') => 'pending',
            default => 'info',
        };
    }
}
