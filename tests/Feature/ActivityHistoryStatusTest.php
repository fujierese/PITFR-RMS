<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Support\ActivityStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Additional Enhancements, Activity History: each entry shows an icon and colour that
 * match what it records, instead of the first letter of the action.
 */
class ActivityHistoryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_actions_map_to_the_right_status(): void
    {
        $expected = [
            'final_approved' => 'approved',
            'custodian_endorsed' => 'approved',
            'availability_confirmed' => 'approved',
            'revision_requested' => 'revision',
            'needs_reschedule' => 'revision',
            'venue_availability_unavailable' => 'revision',
            'final_rejected' => 'rejected',
            'equipment_status_rejected' => 'rejected',
            'equipment_declined' => 'rejected',
            'outsider_inquiry_not_endorsed' => 'rejected',
            'cancellation_requested' => 'pending',
            'igp_review_started' => 'pending',
            'cancelled' => 'cancelled',
            'revision_expired' => 'cancelled',
            'submitted' => 'submitted',
            'equipment_issue_reported' => 'warning',
            'urgent_conflict_detected' => 'warning',
            'requestor_edited' => 'edited',
        ];
        foreach ($expected as $action => $status) {
            $this->assertSame($status, ActivityStatus::forAction($action), $action);
        }
        $this->assertSame('approved', ActivityStatus::forAction('equipment_returned', true));
        $this->assertSame('pending', ActivityStatus::forAction('equipment_returned', false));
    }

    public function test_history_shows_icons_not_letters(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $request = FacilityRequest::create([
            'control_number' => 'FER-HIS-1',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'History icons',
            'expected_participants' => 10,
            'start_date' => now()->addDays(4)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => ['Covered Court'],
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'approved',
        ]);
        $request->addHistory('submitted', 'Request submitted', $requester->id);
        $request->addHistory('final_rejected', 'Final decline issued', $requester->id);

        $html = $this->actingAs($requester)->get(route('request.show', $request))->assertOk()->getContent();
        $this->assertStringContainsString('data-activity-status="submitted"', $html);
        $this->assertStringContainsString('data-activity-status="rejected"', $html);
        $this->assertStringContainsString('bg-rose-600', $html);
    }
}
