<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationSchedule extends Model
{
    use SoftDeletes;
    protected $table = 'reservation_schedules';

    protected $fillable = ['facility_request_id', 'start_datetime', 'end_datetime'];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }
}
