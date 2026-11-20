<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use \App\Models\Concerns\SerializesDatesInAppTimezone;

    protected $fillable = ['holiday_date', 'name', 'type', 'description'];

    protected $casts = [
        'holiday_date' => 'date',
    ];
}
