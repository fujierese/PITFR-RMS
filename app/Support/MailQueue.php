<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * NTF-02: decides how notification emails are delivered and reports on delivery.
 *
 * The scheduler (cron, every minute) records a heartbeat and starts a queue worker.
 * While that heartbeat is fresh, mail is queued; otherwise it is sent immediately,
 * so emails are never stranded in a queue that nothing processes.
 */
class MailQueue
{
    public const HEARTBEAT_KEY = 'pitfr:queue-worker-heartbeat';

    public const LAST_SENT_KEY = 'pitfr:mail-last-sent';

    public const LAST_FAILED_KEY = 'pitfr:mail-last-failed';

    /** How long a heartbeat counts as "the worker is running". */
    public const HEARTBEAT_TTL_SECONDS = 300;

    public static function connection(): string
    {
        $default = (string) config('queue.default', 'sync');

        if ($default === 'sync' || ! self::workerIsRunning() || ! self::queueTablesExist()) {
            return 'sync';
        }

        return $default;
    }

    /** Queued mail needs both the jobs table and the failed_jobs table. */
    public static function queueTablesExist(): bool
    {
        if (config('queue.connections.' . config('queue.default') . '.driver') !== 'database') {
            return true;
        }

        return (bool) rescue(fn () => Cache::remember('pitfr:queue-tables-exist', now()->addMinutes(10),
            fn () => Schema::hasTable('jobs') && Schema::hasTable('failed_jobs')), false, false);
    }

    public static function recordHeartbeat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, now()->getTimestamp(), now()->addDay());
    }

    public static function workerIsRunning(): bool
    {
        try {
            $beat = Cache::get(self::HEARTBEAT_KEY);
        } catch (Throwable) {
            return false;
        }

        return is_numeric($beat) && (now()->getTimestamp() - (int) $beat) <= self::HEARTBEAT_TTL_SECONDS;
    }

    /**
     * Figures for the Supply Office "Email delivery" panel.
     *
     * @return array{worker_running: bool, last_heartbeat: ?\Illuminate\Support\Carbon, queued: int, oldest_queued: ?\Illuminate\Support\Carbon, failed: int, last_sent: ?\Illuminate\Support\Carbon, last_failed: ?\Illuminate\Support\Carbon, last_failure_reason: ?string, mode: string}
     */
    public static function status(): array
    {
        $beat = rescue(fn () => Cache::get(self::HEARTBEAT_KEY), null, false);
        $lastFailed = rescue(fn () => Cache::get(self::LAST_FAILED_KEY), null, false);
        $lastSent = rescue(fn () => Cache::get(self::LAST_SENT_KEY), null, false);

        $queued = 0;
        $oldest = null;
        if (Schema::hasTable('jobs')) {
            $queued = (int) DB::table('jobs')->count();
            $oldestAt = DB::table('jobs')->min('created_at');
            $oldest = $oldestAt ? \Illuminate\Support\Carbon::createFromTimestamp((int) $oldestAt) : null;
        }
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        return [
            'worker_running' => self::workerIsRunning(),
            'last_heartbeat' => is_numeric($beat) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $beat) : null,
            'queued' => $queued,
            'oldest_queued' => $oldest,
            'failed' => $failed,
            'last_sent' => is_numeric($lastSent) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $lastSent) : null,
            'last_failed' => is_array($lastFailed) && isset($lastFailed['at']) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $lastFailed['at']) : null,
            'last_failure_reason' => is_array($lastFailed) ? ($lastFailed['reason'] ?? null) : null,
            'mode' => self::connection() === 'sync' ? 'immediate' : 'queued',
        ];
    }
}
