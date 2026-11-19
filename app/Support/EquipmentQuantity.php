<?php

namespace App\Support;

/**
 * REQ-12: one quantity rule for every route that requests equipment. A quantity is a
 * whole number from 1 to MAX, every quantity belongs to a selected item, and a selected
 * item always has a quantity. Stock limits are checked separately, per schedule.
 */
final class EquipmentQuantity
{
    public const MAX = 1000;

    /** Validation rules for one quantity value. */
    public static function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'integer', 'min:1', 'max:' . self::MAX];
    }

    /** Validation messages for quantity fields matching the given wildcard key. */
    public static function messages(string $key): array
    {
        return [
            "{$key}.required" => 'Enter a quantity for each selected equipment item.',
            "{$key}.integer" => 'Equipment quantities must be whole numbers.',
            "{$key}.min" => 'Equipment quantities must be at least 1.',
            "{$key}.max" => 'Equipment quantities cannot be more than ' . self::MAX . '.',
        ];
    }

    /**
     * Names that have a quantity but are not among the selected items.
     *
     * @param  array<string, mixed>  $quantities
     * @param  list<string>  $selected
     * @return list<string>
     */
    public static function unmatched(array $quantities, array $selected, ?callable $canonicalize = null): array
    {
        $canonicalize ??= static fn (string $name): string => trim($name);
        $selectedKeys = array_map(static fn ($name): string => mb_strtolower($canonicalize((string) $name)), $selected);
        $unmatched = [];
        foreach (array_keys($quantities) as $name) {
            if (! in_array(mb_strtolower($canonicalize((string) $name)), $selectedKeys, true)) {
                $unmatched[] = (string) $name;
            }
        }

        return $unmatched;
    }

    public static function unmatchedMessage(array $names, ?string $venue = null): string
    {
        return ($venue ? "{$venue}: " : '') . 'A quantity was entered for ' . implode(', ', $names)
            . ', which is not selected. Select the item or clear its quantity.';
    }
}
