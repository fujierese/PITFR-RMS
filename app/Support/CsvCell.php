<?php

namespace App\Support;

/**
 * REQ-15: text typed by users (activity names, departments, remarks) is written to
 * CSV exports as plain text. A cell that starts with =, +, -, or @ (after any leading
 * spaces) would be run as a formula by Excel or Google Sheets, so it gets a leading
 * apostrophe, which spreadsheets show as plain text. Same rule as the usage and audit
 * exports already use; numbers are left as they are.
 */
final class CsvCell
{
    public static function row(array $cells): array
    {
        return array_map([self::class, 'cell'], $cells);
    }

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[\s\t\r]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }
}
