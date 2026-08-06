<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestHistory extends Model
{
    protected $fillable = ['facility_request_id', 'user_id', 'action', 'detail', 'occurred_at'];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function facilityRequest()
    {
        return $this->belongsTo(FacilityRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
