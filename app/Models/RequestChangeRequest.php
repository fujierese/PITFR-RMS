<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestChangeRequest extends Model
{
    protected $fillable = [
        'facility_request_id',
        'requested_by_id',
        'reason',
        'status',
        'decided_by_id',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}