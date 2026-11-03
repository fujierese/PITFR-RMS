<?php

namespace App\Notifications\Concerns;

trait UsesNotificationPreferences
{
    protected function channelsWithPreference(object $notifiable, string $preference, array $channels): array
    {
        $preferences = $notifiable->notification_preferences ?? [];
        if (! is_array($preferences)) {
            return $channels;
        }

        $enabled = $preferences[$preference] ?? (
            $preference === 'reservation_reminders'
                ? ($preferences['request_updates'] ?? true)
                : true
        );

        if ($enabled) {
            return $channels;
        }

        return [];
    }
}
