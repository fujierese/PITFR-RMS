<?php

namespace App\Providers;

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One password policy for registration, resets and every settings page.
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->symbols());

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            Log::error('Notification delivery failed.', [
                'notification' => $event->notification::class,
                'channel' => $event->channel,
                'notifiable_type' => $event->notifiable::class,
                'notifiable_id' => method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : null,
                'exception' => $event->data['exception'] ?? null,
            ]);
        });
    }
}
