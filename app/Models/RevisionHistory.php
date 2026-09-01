<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RevisionHistory extends Model
{
    protected $table = 'revision_histories';

    protected $fillable = [
        'facility_request_id',
        'revised_by_id',
        'old_start_date',
        'old_end_date',
        'old_start_time',
        'old_end_time',
        'old_venue',
        'old_equipment',
        'old_equipment_quantities',
        'new_start_date',
        'new_end_date',
        'new_start_time',
        'new_end_time',
        'new_venue',
        'new_equipment',
        'new_equipment_quantities',
        'revision_reason',
        'conflict_detected',
        'conflict_details',
        'override_conflict',
        'override_reason',
        'requestor_notified_at',
        'custodian_notified_at',
    ];

    protected $casts = [
        'old_start_date' => 'date',
        'old_end_date' => 'date',
        'new_start_date' => 'date',
        'new_end_date' => 'date',
        'old_venue' => 'array',
        'old_equipment' => 'array',
        'old_equipment_quantities' => 'array',
        'new_venue' => 'array',
        'new_equipment' => 'array',
        'new_equipment_quantities' => 'array',
        'conflict_detected' => 'boolean',
        'override_conflict' => 'boolean',
        'requestor_notified_at' => 'datetime',
        'custodian_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Revision belongs to a FacilityRequest
     */
    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }

    /**
     * Relationship: Revision was created by a User (admin/supply office)
     */
    public function revisedBy()
    {
        return $this->belongsTo(User::class, 'revised_by_id');
    }

    /**
     * Get human-readable summary of what changed
     */
    public function getSummary(): string
    {
        $changes = [];

        if ($this->old_start_date !== $this->new_start_date) {
            $changes[] = "Date: {$this->old_start_date} → {$this->new_start_date}";
        }

        if ($this->old_start_time !== $this->new_start_time || $this->old_end_time !== $this->new_end_time) {
            $changes[] = "Time: {$this->old_start_time}-{$this->old_end_time} → {$this->new_start_time}-{$this->new_end_time}";
        }

        if ($this->old_venue !== $this->new_venue) {
            $oldVenues = implode(', ', (array) $this->old_venue);
            $newVenues = implode(', ', (array) $this->new_venue);
            $changes[] = "Venue: {$oldVenues} → {$newVenues}";
        }

        return implode(' | ', $changes);
    }

    /**
     * Mark requestor as notified
     */
    public function markRequestorNotified(): void
    {
        $this->update(['requestor_notified_at' => now()]);
    }

    /**
     * Mark custodian as notified
     */
    public function markCustodianNotified(): void
    {
        $this->update(['custodian_notified_at' => now()]);
    }

    /**
     * Check if this revision had conflicts
     */
    public function hadConflicts(): bool
    {
        return $this->conflict_detected && !$this->override_conflict;
    }

    /**
     * Check if admin overrode conflicts
     */
    public function overrodeConflicts(): bool
    {
        return $this->override_conflict && $this->conflict_detected;
    }
}
