<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Notifications\RequestStatusChanged;
use Tests\TestCase;

class RequestStatusChangedNotificationTest extends TestCase
{
    public function test_it_skips_mail_channel_when_notifiable_has_no_email_address(): void
    {
        $request = new FacilityRequest([
            'id' => 1,
            'control_number' => 'FER-2026-001',
            'name_of_activity' => 'Board Meeting',
            'start_date' => '2026-01-01',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $notifiable = new class {
            public string $name = 'Sample User';
        };

        $notification = new RequestStatusChanged($request, 'approved', 'All set');

        $this->assertSame(['mail', 'database', 'broadcast'], $notification->via($notifiable));
    }
}
