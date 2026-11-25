<?php

namespace App\Support;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-19: venue and requestor rules, checked on the server for every route that sets
 * a request's venues (web form, edit, API, Supply Office schedule change) and again
 * at review and approval.
 * - A venue closed to outsiders (the Gym) cannot be booked by an outsider account.
 * - A venue that asks for the activity type (the Gym) needs one of ACTIVITY_TYPES;
 *   "other" needs a short description. Nothing is refused for its type: the choice
 *   is recorded and shown to the Supply Office.
 * Both are settings on each venue (Venue Management). A built-in venue with no
 * record yet follows the same default the migration gives the Gym.
 */
final class VenueRules
{
    /** Activity categories (client list, Nov 2026). */
    public const ACTIVITY_TYPES = [
        'institutional' => 'Institutional',
        'student_organization' => 'Student Organization',
        'sports_recreation' => 'Sports and Recreation',
        'cultural_arts' => 'Cultural and Arts',
        'community_outreach' => 'Community Outreach',
        'other' => 'Other',
    ];

    /** What each category covers, shown with the choice. */
    public const ACTIVITY_TYPE_HINTS = [
        'institutional' => 'Official meetings, institutional programs',
        'student_organization' => 'Organization activities, leadership training',
        'sports_recreation' => 'Sports events, tournaments',
        'cultural_arts' => 'Festivals, performances, cultural programs',
        'community_outreach' => 'Extension activities, community programs',
        'other' => 'Activities that don\'t fit the categories',
    ];

    /** Earlier activity types, kept so existing requests still show what was chosen. */
    public const LEGACY_ACTIVITY_TYPES = [
        'prom' => 'Prom',
        'orientation' => 'Orientation',
        'seminar' => 'Seminar',
        'pickleball' => 'Pickleball',
        'badminton' => 'Badminton',
    ];

    /** @var array<string, array{open_to_outsiders: bool, requires_activity_type: bool}> */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** @return array{open_to_outsiders: bool, requires_activity_type: bool} */
    public static function forVenue(string $venueName): array
    {
        $key = mb_strtolower(trim($venueName));
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $isGym = str_contains($key, 'gym');
        $rules = ['open_to_outsiders' => ! $isGym, 'requires_activity_type' => $isGym];

        if (Schema::hasColumn('venues', 'open_to_outsiders')) {
            $lookup = $key === 'balay alumni' ? ['balay alumni', 'balay alumni hall'] : [$key];
            $venue = Venue::query()
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(name))'), $lookup)
                ->first(['open_to_outsiders', 'requires_activity_type']);
            if ($venue) {
                $rules = [
                    'open_to_outsiders' => (bool) $venue->open_to_outsiders,
                    'requires_activity_type' => (bool) $venue->requires_activity_type,
                ];
            }
        }

        return self::$cache[$key] = $rules;
    }

    public static function openToOutsiders(string $venueName): bool
    {
        return self::forVenue($venueName)['open_to_outsiders'];
    }

    public static function requiresActivityType(string $venueName): bool
    {
        return self::forVenue($venueName)['requires_activity_type'];
    }

    /** Venues in the list this requestor may not book. */
    public static function closedVenuesFor(?User $requestor, array $venueNames): array
    {
        if (! $requestor || ! $requestor->isOutsider()) {
            return [];
        }

        return array_values(array_filter($venueNames, fn ($name): bool => is_string($name) && ! self::openToOutsiders($name)));
    }

    public static function closedVenueMessage(array $closedVenues): string
    {
        return implode(', ', $closedVenues) . (count($closedVenues) === 1 ? ' is' : ' are')
            . ' not open to outside requestors. Choose another venue.';
    }

    /**
     * Error for one venue's activity type, or null when it is fine (or not needed).
     */
    public static function activityTypeError(string $venueName, mixed $type, mixed $detail): ?string
    {
        if (! self::requiresActivityType($venueName)) {
            return null;
        }
        if (! is_string($type) || ! array_key_exists($type, self::ACTIVITY_TYPES)) {
            return "{$venueName}: choose the type of activity (" . implode(', ', array_slice(self::ACTIVITY_TYPES, 0, 5)) . ', or Other).';
        }
        if ($type === 'other' && (! is_string($detail) || trim($detail) === '')) {
            return "{$venueName}: describe the activity.";
        }
        if (is_string($detail) && mb_strlen($detail) > 200) {
            return "{$venueName}: keep the activity description to 200 characters.";
        }

        return null;
    }

    /** @return array{activity_type: ?string, activity_type_detail: ?string} */
    public static function activityFields(string $venueName, mixed $type, mixed $detail): array
    {
        if (! self::requiresActivityType($venueName) || ! is_string($type) || ! array_key_exists($type, self::ACTIVITY_TYPES)) {
            return ['activity_type' => null, 'activity_type_detail' => null];
        }

        return [
            'activity_type' => $type,
            'activity_type_detail' => $type === 'other' && is_string($detail) ? trim($detail) : null,
        ];
    }

    public static function activityLabel(?string $type, ?string $detail): ?string
    {
        $label = self::ACTIVITY_TYPES[$type ?? ''] ?? self::LEGACY_ACTIVITY_TYPES[$type ?? ''] ?? null;
        if (! $type || $label === null) {
            return null;
        }

        return $type === 'other' && $detail ? $label . ': ' . $detail : $label;
    }

    /** Message when a request's requestor may not book one of its venues. */
    public static function requestMessage(FacilityRequest $request): ?string
    {
        $requestor = $request->requested_by_id ? $request->requester : null;
        $closed = self::closedVenuesFor($requestor, $request->getVenueNames());

        return $closed === [] ? null : self::closedVenueMessage($closed);
    }
}
