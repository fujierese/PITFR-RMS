<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use \App\Models\Concerns\SerializesDatesInAppTimezone;

    protected $fillable = ['name', 'custodian_id', 'capacity', 'is_active', 'open_to_outsiders', 'requires_activity_type'];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'open_to_outsiders' => 'boolean',
        'requires_activity_type' => 'boolean',
    ];

    protected static function booted(): void
    {
        // REQ-19: venue rules are cached per request; a saved venue clears them.
        static::saved(fn () => \App\Support\VenueRules::flush());
        static::deleted(fn () => \App\Support\VenueRules::flush());

        static::updated(function (self $venue): void {
            if ($venue->wasChanged('custodian_id')) {
                FacilityRequest::reopenVenueEndorsementsAfterReassignment($venue, $venue->getOriginal('name'));
                // REQ-20: note the change on its bookings and tell the new custodian.
                \App\Support\VenueLifecycle::custodianChanged($venue, $venue->getOriginal('custodian_id') ? (int) $venue->getOriginal('custodian_id') : null);
            }
            // REQ-20: bookings are kept; they are noted and their requestors and the Supply
            // Office are told (whoever made the change: custodian or Supply Office).
            if ($venue->wasChanged('is_active')) {
                \App\Support\VenueLifecycle::activeChanged($venue);
                \App\Support\ResourceAudit::statusChanged('venue', $venue->name, (bool) $venue->is_active);
            }

            // REQ-02: lowering capacity below existing bookings is recorded on each affected request.
            $old = $venue->getOriginal('capacity');
            if ($venue->wasChanged('capacity') && $venue->capacity !== null && ($old === null || (int) $venue->capacity < (int) $old)) {
                foreach (FacilityRequest::activeRequestsAboveCapacity($venue, (int) $venue->capacity) as $request) {
                    $request->addHistory(
                        'venue_capacity_lowered',
                        "{$venue->name} capacity changed from " . ($old ?? 'not set') . " to {$venue->capacity}; this booking has "
                            . $request->participantsForVenue((string) $venue->name) . ' participants. The Supply Office should review it.',
                        auth()->user()?->id,
                    );
                }
            }
        });
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function requestVenues()
    {
        return $this->hasMany(RequestVenue::class);
    }
}