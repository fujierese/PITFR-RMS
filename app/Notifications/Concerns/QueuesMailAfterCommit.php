<?php

namespace App\Notifications\Concerns;

use App\Support\MailQueue;

/**
 * NTF-02: send the email part of a notification from the queue. Classes using this
 * trait implement ShouldQueueAfterCommit, so nothing is queued until the database
 * transaction that triggered it has committed. In-app (database) and
 * real-time (broadcast) notifications still go out immediately.
 *
 * If no queue worker has run recently (for example, the cron job is missing),
 * mail falls back to sending immediately so nothing waits in an unprocessed queue.
 */
trait QueuesMailAfterCommit
{
    /** Retries for a mail send that fails (SMTP timeouts, provider throttling). */
    public int $tries = 3;

    /** Seconds to wait before each retry (short: the first send already failed). */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function viaConnections(): array
    {
        return [
            'mail' => MailQueue::connection(),
            'database' => 'sync',
            'broadcast' => 'sync',
        ];
    }
}
