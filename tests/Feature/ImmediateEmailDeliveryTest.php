<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Follow-up fixes: emails go out right after the action (no queue worker), with the
 * in-app notification; a failed email never blocks the in-app one, is logged, and is
 * retried from the queue.
 */
class ImmediateEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from' => ['address' => 'pitfr@example.test', 'name' => 'PITFR']]);
    }

    private function notifyOnce(User $user, FacilityRequest $request): void
    {
        $user->notify(new RequestStatusChanged($request, 'approved'));
    }

    public function test_email_and_in_app_notification_are_sent_together_without_a_worker(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'username' => 'ana@example.test']);
        $request = FacilityRequest::factory()->create(['requested_by_id' => $user->id]);

        $this->assertSame('after_response', (new RequestStatusChanged($request, 'approved'))->viaConnections()['mail']);
        $this->notifyOnce($user, $request);

        $this->assertSame(1, $user->notifications()->count(), 'In-app notification saved.');
        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent, 'One email, sent immediately (no queued job).');
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertStringContainsString($request->control_number, $sent->first()->getOriginalMessage()->getSubject());
    }

    public function test_smtp_failure_keeps_the_in_app_notice_logs_and_queues_a_retry(): void
    {
        Mail::extend('broken', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection to smtp.gmail.com timed out');
            }

            public function __toString(): string
            {
                return 'broken://';
            }
        });
        config(['mail.mailers.broken' => ['transport' => 'broken'], 'mail.default' => 'broken']);
        Log::spy();

        $user = User::factory()->create(['role' => 'requestor', 'username' => 'ana@example.test']);
        $request = FacilityRequest::factory()->create(['requested_by_id' => $user->id]);
        $this->notifyOnce($user, $request);

        $this->assertSame(1, $user->notifications()->count(), 'The in-app notification is not blocked.');
        $this->assertSame(1, DB::table('jobs')->count(), 'The failed email is queued for retry.');
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'Notification email failed')
            && str_contains($context['error'] ?? '', 'timed out')
            && in_array('ana@example.test', $context['recipients'] ?? [], true));
    }

    public function test_mail_timeout_is_set(): void
    {
        $this->assertSame(10, config('mail.mailers.smtp.timeout'));
    }
}
