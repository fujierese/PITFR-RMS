<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use \App\Models\Concerns\SerializesDatesInAppTimezone;

    protected $fillable = ['name', 'custodian_id', 'capacity', 'is_active'];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $venue): void {
            if ($venue->wasChanged('custodian_id')) {
                FacilityRequest::reopenVenueEndorsementsAfterReassignment($venue, $venue->getOriginal('name'));
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