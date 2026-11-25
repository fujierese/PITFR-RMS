<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RequestStatusChanged;
use App\Support\MailQueue;
use Database\Seeders\CollegeDepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * "PITFR Fixes and Additional Requirements", part A.
 */
class FixesAndRequirementsATest extends TestCase
{
    use RefreshDatabase;

    public function test_a1_account_without_college_picks_it_on_the_form(): void
    {
        $this->seed(CollegeDepartmentSeeder::class);
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty', 'college_id' => null, 'department_id' => null, 'department' => null]);

        $this->actingAs($user)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('data-college-picker', false)
            ->assertSee('<select id="college_id" name="college_id" required', false);
    }

    public function test_a1_account_with_college_keeps_the_read_only_value(): void
    {
        $this->seed(CollegeDepartmentSeeder::class);
        $department = \App\Models\Department::query()->firstOrFail();
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty', 'college_id' => $department->college_id, 'department_id' => $department->id]);

        $this->actingAs($user)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertDontSee('data-college-picker', false)
            ->assertSee('<input type="hidden" name="college_id" value="' . $department->college_id . '">', false);
    }

    public function test_a1_confirm_dialog_waits_for_the_form_checks(): void
    {
        $user = User::factory()->create(['role' => 'requestor']);

        $this->actingAs($user)->get(route('requestor.index'))
            ->assertOk()
            ->assertSee("new CustomEvent('swal-before-confirm', { cancelable: true })", false);
    }

    public function test_a1_role_label_uses_department_for_faculty(): void
    {
        $this->seed(CollegeDepartmentSeeder::class);
        $department = \App\Models\Department::query()->firstOrFail();
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty', 'department_id' => $department->id, 'college_id' => $department->college_id]);

        $this->actingAs($user)->get(route('requestor.index'))
            ->assertOk()
            ->assertSee(e($department->name) . ' — Faculty', false);
    }

    public function test_a1_student_without_organization_no_longer_shows_placeholder_label(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->actingAs($user)->get(route('requestor.index'))
            ->assertOk()
            ->assertDontSee('Student Organization — Student Representative');
    }

    public function test_a4_successful_submission_tells_the_browser_to_clear_the_draft(): void
    {
        $user = User::factory()->create(['role' => 'requestor']);

        $this->actingAs($user)
            ->withSession(['request_draft_submitted' => true])
            ->get(route('requestor.index', ['tab' => 'requests']))
            ->assertOk()
            ->assertSee('data-request-draft-submitted="1"', false)
            ->assertSee('data-draft-owner="' . $user->id . '"', false);
    }

    public function test_a5_requested_window_ends_at_its_real_end_time(): void
    {
        $html = $this->get(route('calendar.embed', ['venue' => 'Gymnasium', 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'start_time' => '08:00', 'end_time' => '17:00']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString("':59:59'", $html);
        $this->assertStringContainsString('const highlightVisualEndTime = highlightEndTime;', $html);
        $this->assertStringContainsString('if (endTime <= startTime) {', $html);
        $this->assertStringContainsString("setOption('slotMinTime', nextMinTime)", $html);
    }

    public function test_ntf02_mail_waits_for_a_running_worker_and_in_app_stays_immediate(): void
    {
        config(['queue.default' => 'database']);
        $notification = new RequestStatusChanged(\App\Models\FacilityRequest::factory()->create(), 'approved');
        // Default now: sent right after the response, no worker needed.
        $this->assertSame('after_response', $notification->viaConnections()['mail']);
        // Optional worker mode (MAIL_DELIVERY=queue) keeps the earlier behaviour.
        config(['pitfr.mail_delivery' => 'queue']);

        Cache::forget(MailQueue::HEARTBEAT_KEY);
        $this->assertSame('sync', $notification->viaConnections()['mail'], 'No worker: send immediately.');

        MailQueue::recordHeartbeat();
        Cache::forget('pitfr:queue-tables-exist');
        $this->assertSame('database', $notification->viaConnections()['mail'], 'Worker running: queue the email.');
        $this->assertSame('sync', $notification->viaConnections()['database']);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueueAfterCommit::class, $notification);
    }

    public function test_ntf02_supply_office_sees_email_delivery_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('supply-office.settings'))
            ->assertOk()
            ->assertSee('Email delivery')
            ->assertSee('Waiting to send');
    }
}
