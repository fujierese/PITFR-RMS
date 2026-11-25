<?php

namespace App\Queue;

use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Notification emails sent right after the HTTP response (within seconds), with no
 * queue worker. The user never waits for SMTP, and the in-app notification is
 * already saved by then.
 *
 * - In a web request the email is sent after the response is returned.
 * - In console (scheduler, artisan, tests) it is sent at once; nobody is waiting.
 * - If sending fails, the error is logged and the email is handed to the fallback
 *   queue (database), which retries it with a short backoff and finally records it
 *   in failed_jobs. One failed email never stops the others.
 */
class AfterResponseQueue extends SyncQueue
{
    public function __construct(private readonly ?string $fallbackConnection = 'database', $dispatchAfterCommit = false)
    {
        parent::__construct($dispatchAfterCommit);
    }

    public function push($job, $data = '', $queue = null)
    {
        $send = function () use ($job, $data, $queue): void {
            try {
                parent::push($job, $data, $queue);
            } catch (Throwable $e) {
                $this->handleFailure($job, $e);
            }
        };

        if (app()->runningInConsole()) {
            $send();

            return null;
        }

        \Illuminate\Support\defer($send);

        return null;
    }

    private function handleFailure(mixed $job, Throwable $e): void
    {
        $recipients = [];
        if (is_object($job) && property_exists($job, 'notifiables')) {
            foreach ((array) ($job->notifiables instanceof \Illuminate\Support\Collection ? $job->notifiables->all() : $job->notifiables) as $notifiable) {
                $recipients[] = is_object($notifiable) ? ($notifiable->email ?? $notifiable->username ?? $notifiable::class) : (string) $notifiable;
            }
        }
        Log::error('Notification email failed; queued for retry.', [
            'event' => is_object($job) && property_exists($job, 'notification') ? $job->notification::class : (is_object($job) ? $job::class : (string) $job),
            'recipients' => $recipients,
            'error' => $e->getMessage(),
        ]);

        if (! $this->fallbackConnection || ! is_object($job)) {
            return;
        }
        try {
            Queue::connection($this->fallbackConnection)->later(now()->addSeconds(10), $job, '', $job->queue ?? null);
        } catch (Throwable $retryError) {
            Log::error('Could not queue the failed email for retry.', ['error' => $retryError->getMessage()]);
        }
    }
}
