<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REQ-10: a clash with a request still under review only warns the requestor;
 * a clash with an approved booking still marks the venue unavailable.
 */
class PendingConflictAdvisoryTest extends TestCase
{
    use RefreshDatabase;

    private function booking(string $status, string $activity): void
    {
        $date = now()->addDays(7)->toDateString();
        FacilityRequest::create([
            'control_number' => 'TEST-REQ10-' . uniqid(),
            'date_requested' => now()->toDateString(),
            'department' => 'IT Department',
            'name_of_activity' => $activity,
            'expected_participants' => 20,
            'start_date' => $date, 'end_date' => $date,
            'start_time' => '09:00', 'end_time' => '11:00',
            'venue' => ['Gymnasium'],
            'requested_by_id' => User::factory()->create(['role' => 'requestor'])->id,
            'status' => $status,
            'venue_status' => $status,
            'equipment_status' => 'approved',
        ]);
    }

    private function check()
    {
        $date = now()->addDays(7)->toDateString();
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        return $this->actingAs($requestor)->getJson(route('requestor.venue.availability', [
            'venues' => ['Gymnasium'],
            'start_date' => $date, 'end_date' => $date,
            'start_time' => '10:00', 'end_time' => '12:00',
        ]))->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'is_active' => true,
            'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id]);
    }

    public function test_pending_overlap_is_advisory_only(): void
    {
        $this->booking('pending', 'Pending booking');

        $this->check()
            ->assertJsonPath('venues.0.available', true)
            ->assertJsonPath('venues.0.status', 'pending_conflict')
            ->assertJsonPath('venues.0.status_label', 'Pending schedule conflict')
            ->assertJsonPath('venues.0.can_submit', true)
            // Unified workflow: the tentative overlap is named (reference and time) and does not block.
            ->assertJsonPath('venues.0.pending_notice', fn ($notice) => str_starts_with((string) $notice, 'Tentative overlap: another request is waiting for review for this time ('));
    }

    public function test_free_slot_has_no_notice_and_approved_overlap_blocks(): void
    {
        $this->check()->assertJsonPath('venues.0.available', true)->assertJsonPath('venues.0.status', 'available')->assertJsonPath('venues.0.pending_notice', null);

        $this->booking('approved', 'Approved booking');
        $this->check()->assertJsonPath('venues.0.available', false)->assertJsonPath('venues.0.status', 'unavailable')->assertJsonPath('venues.0.can_submit', false)->assertJsonPath('venues.0.pending_notice', null);
    }
}
