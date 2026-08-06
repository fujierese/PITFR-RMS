<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = ['log_name', 'event', 'message', 'causer_type', 'causer_id', 'subject_type', 'subject_id', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];

    public static function record(string $event, string $message, ?string $causerType = null, ?int $causerId = null, ?string $subjectType = null, ?int $subjectId = null, array $properties = []): self
    {
        return static::create([
            'log_name' => 'system',
            'event' => $event,
            'message' => $message,
            'causer_type' => $causerType,
            'causer_id' => $causerId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
        ]);
    }
}
