<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\RequestHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Issue APR-03: the history records the role acted in and the request version,
 * and entries cannot be edited or deleted through the application.
 */
class ApprovalHistoryAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(): FacilityRequest
    {
        return FacilityRequest::factory()->create([
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
            'venue' => ['Gymnasium'],
            'equipment' => [],
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-01',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
    }

    public function test_history_records_the_role_the_user_acted_in(): void
    {
        // One person holding two roles: the stage decides which role is recorded.
        $user = User::factory()->create(['role' => 'supply_office', 'additional_roles' => ['custodian-venue']]);
        $request = $this->makeRequest();

        $venue = $request->addHistory('venue_status_approved', 'Venue confirmed', $user->id);
        $final = $request->addHistory('final_approved', 'Final approval', $user->id);
        $system = $request->addHistory('revision_expired', 'Expired');

        $this->assertSame('custodian-venue', $venue->actor_role);
        $this->assertSame('Custodian', $venue->actorRoleLabel());
        $this->assertSame('supply_office', $final->actor_role);
        $this->assertSame('system', $system->actor_role);
    }

    public function test_request_version_increases_only_when_the_approved_content_changes(): void
    {
        $user = User::factory()->create(['role' => 'supply_office']);
        $request = $this->makeRequest();

        $first = $request->addHistory('submitted', null, $user->id);
        $second = $request->addHistory('availability_confirmed', null, $user->id);

        $request->update(['start_date' => '2026-12-02', 'end_date' => '2026-12-02']);
        $third = $request->fresh()->addHistory('revision_applied', null, $user->id);

        $this->assertSame(1, $first->request_version);
        $this->assertSame(1, $second->request_version);
        $this->assertSame($first->request_fingerprint, $second->request_fingerprint);
        $this->assertSame(2, $third->request_version);
        $this->assertNotSame($second->request_fingerprint, $third->request_fingerprint);
    }

    public function test_history_entries_cannot_be_edited_or_deleted(): void
    {
        $request = $this->makeRequest();
        $entry = $request->addHistory('final_approved', 'Final approval granted');

        try {
            $entry->update(['detail' => 'changed']);
            $this->fail('History entry was edited.');
        } catch (LogicException) {
        }

        try {
            $entry->delete();
            $this->fail('History entry was deleted.');
        } catch (LogicException) {
        }

        $this->assertDatabaseHas('request_histories', ['id' => $entry->id, 'detail' => 'Final approval granted']);
    }

    public function test_audit_log_shows_role_and_version(): void
    {
        // An administrator who gives final approval is recorded as Admin, not as Supply Office.
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->makeRequest();
        $request->addHistory('final_approved', 'Final approval granted', $admin->id);

        $this->actingAs($admin)->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertSee('as Admin')
            ->assertSee('Version 1');
    }
}
