<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestStatusHistory extends Model
{
    use SoftDeletes;
    protected $table = 'request_status_history';

    protected $fillable = ['facility_request_id', 'status', 'detail'];

    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }
}
