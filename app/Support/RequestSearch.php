<?php

namespace App\Support;

use App\Models\FacilityRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Additional Enhancements, search: one search rule for every request list.
 *
 * - Case-insensitive, partial matches.
 * - Several words narrow the result: every word must match at least one field
 *   (so "gym prom" finds the Gym prom request).
 * - Fields: control number, activity, purpose, department/office, requester name and
 *   organization, venues, equipment, reservation dates, and statuses (request,
 *   venue review, equipment review, Supply Office decision), using the words people
 *   see ("waiting", "declined", "confirmed"...).
 *
 * It only narrows a query: the caller's role scope (own requests, assigned venues
 * or equipment, everything for the Supply Office) is applied before and stays.
 */
final class RequestSearch
{
    /** Words people type for a status, mapped to the stored value they mean. */
    private const STATUS_WORDS = [
        'waiting' => 'pending',
        'review' => 'pending',
        'declined' => 'rejected',
        'decline' => 'rejected',
        'denied' => 'rejected',
        'endorsed' => 'approved',
        'confirmed' => 'confirmed',
        'unavailable' => 'unavailable',
        'canceled' => 'cancelled',
        'reschedule' => 'needs_reschedule',
        'revision' => 'needs_reschedule',
    ];

    /** @return list<string> */
    public static function terms(?string $search): array
    {
        $search = mb_strtolower(trim((string) $search));
        if ($search === '') {
            return [];
        }
        $terms = preg_split('/\s+/u', mb_substr($search, 0, 200)) ?: [];

        return array_values(array_slice(array_unique(array_filter($terms, fn ($t) => $t !== '')), 0, 8));
    }

    /**
     * @param  bool  $withRequester  include requester name/organization (not needed on a requestor's own list)
     */
    public static function apply(Builder $query, ?string $search, bool $withRequester = true): Builder
    {
        foreach (self::terms($search) as $term) {
            $like = '%' . self::escapeLike($term) . '%';
            $status = self::STATUS_WORDS[$term] ?? null;
            $date = self::dateFor($term);

            $query->where(function (Builder $match) use ($like, $status, $date, $withRequester): void {
                $match->whereRaw("LOWER(control_number) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(name_of_activity) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(purpose, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(department, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(start_date, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(end_date, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(status, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(venue_status, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(equipment_status, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(supply_office_review_status, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('requestVenues', fn (Builder $v) => $v->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]))
                    ->orWhereHas('requestEquipment', fn (Builder $e) => $e->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]))
                    ->orWhereRaw("LOWER(COALESCE(venue, '')) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(COALESCE(equipment, '')) LIKE ? ESCAPE '!'", [$like]);

                if ($withRequester) {
                    $match->orWhereHas('requester', function (Builder $r) use ($like): void {
                        $r->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("LOWER(COALESCE(office_or_organization, '')) LIKE ? ESCAPE '!'", [$like]);
                    });
                }
                if ($status !== null) {
                    foreach (['status', 'venue_status', 'equipment_status', 'supply_office_review_status'] as $column) {
                        $match->orWhere($column, $status);
                    }
                }
                if ($date !== null) {
                    $match->orWhere(function (Builder $d) use ($date): void {
                        $d->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
                    })->orWhereDate('start_date', $date);
                }
            });
        }

        return $query;
    }

    /** The same rule for lists that are already loaded (custodian and IGP lists). */
    public static function matches(FacilityRequest $request, ?string $search, bool $withRequester = true): bool
    {
        $terms = self::terms($search);
        if ($terms === []) {
            return true;
        }

        $haystack = mb_strtolower(implode(' | ', array_filter([
            $request->control_number,
            $request->name_of_activity,
            $request->purpose,
            $request->department,
            $request->start_date?->toDateString(),
            $request->end_date?->toDateString(),
            $request->status,
            $request->venue_status,
            $request->equipment_status,
            $request->supply_office_review_status,
            implode(', ', $request->getVenueNames()),
            implode(', ', $request->getEquipmentItems()),
            $withRequester ? ($request->requester?->name ?? $request->requested_by) : null,
            $withRequester ? $request->requester?->office_or_organization : null,
        ], fn ($value) => $value !== null && $value !== '')));
        $statuses = array_filter([$request->status, $request->venue_status, $request->equipment_status, $request->supply_office_review_status]);

        foreach ($terms as $term) {
            $status = self::STATUS_WORDS[$term] ?? null;
            $date = self::dateFor($term);
            $found = str_contains($haystack, $term)
                || ($status !== null && in_array($status, $statuses, true))
                || ($date !== null && $request->start_date && $request->start_date->toDateString() <= $date
                    && ($request->end_date ?? $request->start_date)->toDateString() >= $date);
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /** A full date typed as 2026-11-24, 11/24/2026 or 24-11-2026 also matches multi-day bookings. */
    private static function dateFor(string $term): ?string
    {
        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'd-m-Y'] as $format) {
            $parsed = \DateTime::createFromFormat('!' . $format, $term);
            if ($parsed && $parsed->format($format) === $term) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
