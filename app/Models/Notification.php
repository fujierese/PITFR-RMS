<?php
namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use \App\Models\Concerns\SerializesDatesInAppTimezone;

    protected $table = 'notifications';

    protected $guarded = [];
}
