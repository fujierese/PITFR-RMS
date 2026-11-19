<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings;

/**
 * REQ-15: Laravel's trimming (spaces, including non-breaking and invisible ones, are
 * removed from both ends, so a blank-looking entry counts as empty), plus removal of
 * control characters such as NUL or BEL anywhere in the text. Tabs and line breaks
 * are kept. Password fields are left exactly as typed.
 */
class CleanTextInput extends TrimStrings
{
    protected function transform($key, $value)
    {
        $value = parent::transform($key, $value);

        if (! is_string($value) || $this->shouldSkip($key, array_merge($this->except, static::$neverTrim))) {
            return $value;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? $value;
    }
}
