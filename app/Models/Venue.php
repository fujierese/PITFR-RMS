<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = ['name', 'custodian_id', 'capacity', 'is_active'];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function requestVenues()
    {
        return $this->hasMany(RequestVenue::class);
    }
}