<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestEquipment extends Model
{
    use SoftDeletes;
    protected $table = 'request_equipment';

    protected $fillable = ['facility_request_id', 'equipment_id', 'name', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function resolvedName(): string
    {
        $relation = $this->relationLoaded('equipment') ? $this->getRelation('equipment') : null;
        $relatedName = $relation instanceof Equipment ? $relation->name : null;

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
