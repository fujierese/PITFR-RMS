<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Additional Part 2: an approved urgent reservation overlapping another reservation. */
class ReservationConflict extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = [
        'urgent_request_id', 'affected_request_id', 'venue_name', 'status', 'resolution',
        'resolved_request_id', 'reason', 'explanation', 'previous_details', 'new_details',
        'detected_by_id', 'resolved_by_id', 'detected_at', 'resolved_at',
    ];

    protected $casts = [
        'previous_details' => 'array',
        'new_details' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function urgentRequest(): BelongsTo
    {
        return $this->belongsTo(FacilityRequest::class, 'urgent_request_id');
    }

    public function affectedRequest(): BelongsTo
    {
        return $this->belongsTo(FacilityRequest::class, 'affected_request_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /** The other reservation in this conflict, seen from $request. */
    public function otherSide(FacilityRequest $request): ?FacilityRequest
    {
        return (int) $this->urgent_request_id === (int) $request->id ? $this->affectedRequest : $this->urgentRequest;
    }
}
