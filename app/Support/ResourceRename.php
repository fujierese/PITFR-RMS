<?php

namespace App\Support;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Venue;

/**
 * REQ-13: requests keep the venue and equipment names they were made with, and
 * availability is matched by name. So a venue or item can't take a name another one
 * already has, and can't be renamed while pending or upcoming requests still use it.
 */
final class ResourceRename
{
    public static function venueError(string $newName, ?Venue $venue = null): ?string
    {
        $newName = trim($newName);
        $duplicate = Venue::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($newName)])
            ->when($venue, fn ($query) => $query->whereKeyNot($venue->getKey()))
            ->exists();
        if ($duplicate) {
            return "Another venue is already named \"{$newName}\". Choose a different name.";
        }

        if ($venue === null || trim((string) $venue->name) === $newName) {
            return null;
        }

        $inUse = self::liveRequests()->where(fn ($query) => $query->matchesVenue((string) $venue->name));

        return self::inUseError('venue', (string) $venue->name, $inUse);
    }

    public static function equipmentError(string $newName, ?Equipment $equipment = null): ?string
    {
        $newName = trim($newName);
        $duplicate = Equipment::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($newName)])
            ->when($equipment, fn ($query) => $query->whereKeyNot($equipment->getKey()))
            ->exists();
        if ($duplicate) {
            return "Another equipment item is already named \"{$newName}\". Choose a different name.";
        }

        if ($equipment === null || trim((string) $equipment->name) === $newName) {
            return null;
        }

        $inUse = self::liveRequests()->where(fn ($query) => $query->matchesEquipment((string) $equipment->name));

        return self::inUseError('equipment item', (string) $equipment->name, $inUse);
    }

    /** Pending requests, and approved ones that have not ended yet. */
    private static function liveRequests()
    {
        return FacilityRequest::query()->where(function ($query): void {
            $query->where('status', 'pending')
                ->orWhere(fn ($approved) => $approved->where('status', 'approved')
                    ->whereDate('end_date', '>=', now()->toDateString()));
        });
    }

    private static function inUseError(string $kind, string $currentName, $query): ?string
    {
        $controlNumbers = $query->orderBy('start_date')->limit(6)->pluck('control_number')->filter()->values();
        if ($controlNumbers->isEmpty()) {
            return null;
        }

        $list = $controlNumbers->take(5)->implode(', ') . ($controlNumbers->count() > 5 ? ', …' : '');

        return "\"{$currentName}\" can't be renamed while pending or upcoming requests use it ({$list}). "
            . "Rename it after they finish, or add a new {$kind} and deactivate this one.";
    }
}
