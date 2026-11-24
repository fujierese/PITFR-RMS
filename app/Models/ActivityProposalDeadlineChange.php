<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** History of the Activity Proposal submission deadline. */
class ActivityProposalDeadlineChange extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['facility_request_id', 'previous_deadline', 'new_deadline', 'kind', 'reason', 'changed_by_id'];

    protected $casts = [
        'previous_deadline' => 'date',
        'new_deadline' => 'date',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
