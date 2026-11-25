<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One uploaded Activity Proposal file. Versions are never overwritten. */
class ActivityProposalVersion extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = [
        'facility_request_id', 'version_number', 'submission_type', 'file_name', 'original_name',
        'uploaded_by_id', 'submitted_at', 'review_status', 'remarks', 'reviewed_by_id', 'reviewed_at', 'imported', 'deadline_at_submission',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'imported' => 'boolean',
    ];

    public function facilityRequest(): BelongsTo
    {
        return $this->belongsTo(FacilityRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function isFinal(): bool
    {
        return $this->submission_type === 'final';
    }

    public function typeLabel(): string
    {
        return $this->isFinal() ? 'Final Copy' : 'Draft / Preliminary Copy';
    }

    public function reviewLabel(): string
    {
        return match ($this->review_status) {
            'verified' => 'Verified',
            'requires_correction' => 'Requires Correction',
            'superseded' => 'Replaced by a newer version',
            default => $this->isFinal() ? 'For Verification' : 'Draft',
        };
    }
}
