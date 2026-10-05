<?php

namespace App\Notifications\Concerns;

trait UsesNotificationPreferences
{
    protected function channelsWithPreference(object $notifiable, string $preference, array $channels): array
    {
        $preferences = $notifiable->notification_preferences ?? [];

        if (!is_array($preferences) || ($preferences[$preference] ?? true)) {
            return $channels;
        }

        return [];
    }
}
