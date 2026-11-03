<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\Facades\Log;

trait UsesWorkflowNotificationChannels
{
    protected function workflowChannels(object $notifiable, array $channels = ['database', 'mail']): array
    {
        $mailAddress = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail')
            : ($notifiable->email ?? null);

        if (in_array('mail', $channels, true) && (! is_string($mailAddress) || filter_var($mailAddress, FILTER_VALIDATE_EMAIL) === false)) {
            Log::warning('Workflow email notification skipped because the recipient address is invalid.', [
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            ]);

            return array_values(array_filter($channels, static fn (string $channel): bool => $channel !== 'mail'));
        }

        return $channels;
    }
}
