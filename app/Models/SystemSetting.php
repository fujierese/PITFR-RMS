<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description', 'is_public'];

    protected $casts = [
        'value' => 'string',
        'is_public' => 'boolean',
    ];

    public static function valueFor(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }
}
