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
        // Notification emails go out right after the response (see App\Queue\AfterResponseQueue).
        \Illuminate\Support\Facades\Queue::extend('after_response', fn () => new \App\Queue\AfterResponseConnector());

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
            if ($event->channel === 'mail') {
                rescue(fn () => \Illuminate\Support\Facades\Cache::put(\App\Support\MailQueue::LAST_FAILED_KEY, [
                    'at' => now()->getTimestamp(),
                    'reason' => \Illuminate\Support\Str::limit((string) (($event->data['exception'] ?? null)?->getMessage() ?? 'Unknown error'), 200),
                ], now()->addDays(30)), null, false);
            }
        });

        // NTF-02: track email delivery so the Supply Office can see whether mail is flowing.
        Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, function ($event): void {
            if ($event->channel !== 'mail') {
                return;
            }
            Log::info('Notification email sent.', [
                'notification' => $event->notification::class,
                'notifiable_id' => method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : null,
            ]);
            rescue(fn () => \Illuminate\Support\Facades\Cache::put(\App\Support\MailQueue::LAST_SENT_KEY, now()->getTimestamp(), now()->addDays(30)), null, false);
        });

        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event): void {
            Log::error('Queued job failed after its retries.', [
                'job' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
            rescue(fn () => \Illuminate\Support\Facades\Cache::put(\App\Support\MailQueue::LAST_FAILED_KEY, [
                'at' => now()->getTimestamp(),
                'reason' => \Illuminate\Support\Str::limit($event->exception->getMessage(), 200),
            ], now()->addDays(30)), null, false);
        });
    }
}
