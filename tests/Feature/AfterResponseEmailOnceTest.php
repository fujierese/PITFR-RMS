<?php

namespace Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Live log: one "password changed" email was sent six times and GoDaddy refused more
 * ("421 too many messages in this connection"). Each job finishing on a non-"deferred"
 * connection made Laravel re-run the after-response callbacks still waiting. Every
 * deferred email must be sent exactly once.
 */
class AfterResponseEmailOnceTest extends TestCase
{
    public function test_each_deferred_email_job_runs_exactly_once(): void
    {
        AfterResponseCountingJob::$runs = [];
        $this->assertSame('deferred', Queue::connection('after_response')->getConnectionName());

        foreach (['a', 'b', 'c'] as $name) {
            \Illuminate\Support\defer(fn () => Queue::connection('after_response')->push(new AfterResponseCountingJob($name)));
        }
        app(DeferredCallbackCollection::class)->invokeWhen();

        $this->assertSame(['a', 'b', 'c'], AfterResponseCountingJob::$runs);
    }
}

class AfterResponseCountingJob implements ShouldQueue
{
    use Queueable;

    public static array $runs = [];

    public function __construct(public string $name)
    {
    }

    public function handle(): void
    {
        self::$runs[] = $this->name;
    }
}
