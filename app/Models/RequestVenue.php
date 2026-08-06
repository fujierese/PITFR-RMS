<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestVenue extends Model
{
    use SoftDeletes;
    protected $table = 'request_venues';

    protected $fillable = ['facility_request_id', 'venue_id', 'name'];

    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function resolvedName(): string
    {
        $relation = $this->relationLoaded('venue') ? $this->getRelation('venue') : null;
        $relatedName = $relation instanceof Venue ? $relation->name : null;

        if (is_string($relatedName) && trim($relatedName) !== '') {
            return trim($relatedName);
        }

        $storedName = $this->getAttribute('name');
        if (is_string($storedName) && trim($storedName) !== '') {
            return trim($storedName);
        }

        return '';
    }
}
