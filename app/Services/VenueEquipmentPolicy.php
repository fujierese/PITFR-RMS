<?php
namespace App\Services;

class VenueEquipmentPolicy
{
    /**
     * Get the default/required equipment for a venue.
     * These are automatically included in the request.
     *
     * @param string $venueName
     * @return array
     */
    public static function getDefaultEquipment(string $venueName): array
    {
        $normalizedVenue = self::normalizeVenueName($venueName);

        return match ($normalizedVenue) {
            'Balay Alumni' => ['Sound System'],
            'Conference Hall & Interaction Center (CHIC)' => ['Sound System'],
            'Gymnasium' => ['Sound System'],
            'PIT Multi-Purpose Gymnasium' => ['Sound System'],
            default => [],
        };
    }

    /**
     * Get equipment that is incompatible with a venue.
     * These cannot be selected when this venue is chosen.
     *
     * @param string $venueName
     * @return array
     */
    public static function getIncompatibleEquipment(string $venueName): array
    {
        $normalizedVenue = self::normalizeVenueName($venueName);

        return match ($normalizedVenue) {
            'Balay Alumni' => [
                'Canopies',
                'Industrial Fans',
                'Iwata Cooler Fans',
                'Monobloc Chairs',
                'Wireless Microphones',
                'Non-Wireless Microphones',
            ],
            default => [],
        };
    }

    /**
     * Check if a venue has required equipment.
     *
     * @param string $venueName
     * @return bool
     */
    public static function hasDefaultEquipment(string $venueName): bool
    {
        return !empty(self::getDefaultEquipment($venueName));
    }

    /**
     * Get all venues that have default equipment.
     *
     * @return array
     */
    public static function getVenuesWithDefaultEquipment(): array
    {
        return [
            'Balay Alumni',
            'Conference Hall & Interaction Center (CHIC)',
            'Gymnasium',
            'PIT Multi-Purpose Gymnasium',
        ];
    }

    private static function normalizeVenueName(string $venueName): string
    {
        $normalized = trim((string) $venueName);

        return match (mb_strtolower($normalized)) {
            'balay alumni hall' => 'Balay Alumni',
            default => $normalized,
        };
    }
}
