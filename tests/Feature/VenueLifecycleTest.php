<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ResourceStatusChanged;
use App\Notifications\VenueCustodianAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * REQ-20: deactivating, reassigning or deleting a venue that has bookings keeps the
 * bookings, notes the change on each pending or upcoming one, tells the people
 * involved, and never deletes a venue requests still refer to.
 */
class VenueLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $requestor;
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->custodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'Old Custodian']);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->venue = Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $this->custodian->id, 'is_active' => true]);
    }

    private function booking(string $status, int $daysFromNow, string $number): FacilityRequest
    {
        $date = now()->addDays($daysFromNow)->toDateString();

        return FacilityRequest::create([
            'control_number' => $number,
            'date_requested' => now()->subDays(30)->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Booking ' . $number,
            'expected_participants' => 20,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Covered Court'],
            'requested_by_id' => $this->requestor->id,
            'status' => $status,
            'venue_status' => $status === 'pending' ? 'pending' : 'approved',
            'equipment_status' => 'approved',
        ]);
    }

    public function test_deactivating_keeps_bookings_notes_them_and_tells_people(): void
    {
        $pending = $this->booking('pending', 5, 'FER-2026-401');
        $upcoming = $this->booking('approved', 8, 'FER-2026-402');
        $past = $this->booking('approved', -8, 'FER-2026-403');

        $this->actingAs($this->admin)->patch(route('supply-office.venues.toggle', $this->venue))
            ->assertSessionHas('success', 'Covered Court was disabled. It can no longer be booked.')
            ->assertSessionHas('warning', fn (string $warning): bool => str_contains($warning, 'FER-2026-401, FER-2026-402')
                && str_contains($warning, '1 approved booking(s) need the Supply Office to confirm or reschedule them'));

        $this->assertFalse($this->venue->fresh()->is_active);
        $this->assertSame('approved', $upcoming->fresh()->status, 'Approved bookings are kept.');
        $this->assertTrue($pending->histories()->where('action', 'venue_deactivated')->exists());
        $this->assertTrue($upcoming->histories()->where('action', 'venue_deactivated')->exists());
        $this->assertFalse($past->histories()->where('action', 'venue_deactivated')->exists(), 'Finished bookings are left alone.');
        Notification::assertSentTo($this->requestor, ResourceStatusChanged::class, fn ($n) => $n->status === 'disabled');
        Notification::assertSentTo($this->admin, ResourceStatusChanged::class);

        $this->actingAs($this->admin)->patch(route('supply-office.venues.toggle', $this->venue))
            ->assertSessionHas('success', 'Covered Court was re-enabled and can be booked again.');
        $this->assertTrue($this->venue->fresh()->is_active);
        $this->assertTrue($pending->histories()->where('action', 'venue_reactivated')->exists());
    }

    public function test_only_the_supply_office_can_enable_or_disable_a_venue(): void
    {
        // QA-2: the custodian cannot disable it...
        $this->actingAs($this->custodian)->patch(route('custodian.venues.toggle', $this->venue))->assertForbidden();
        $this->assertTrue($this->venue->fresh()->is_active);

        // ...nor re-enable a venue the Supply Office disabled, and sees no button for it.
        $this->actingAs($this->admin)->patch(route('supply-office.venues.toggle', $this->venue));
        $this->assertFalse($this->venue->fresh()->is_active);
        $this->actingAs($this->custodian)->patch(route('custodian.venues.toggle', $this->venue))->assertForbidden();
        $this->assertFalse($this->venue->fresh()->is_active);

        $this->actingAs($this->custodian)->get(route('custodian.assignments'))
            ->assertOk()
            ->assertSee('Disabled by the Supply Office.')
            ->assertDontSee(route('custodian.venues.toggle', $this->venue), false);
    }

    public function test_enabling_and_disabling_is_recorded_in_the_audit_history(): void
    {
        $this->actingAs($this->admin)->patch(route('supply-office.venues.toggle', $this->venue));
        $this->actingAs($this->admin)->patch(route('supply-office.venues.toggle', $this->venue));

        $this->assertSame(
            ['Venue "Covered Court" was disabled by ' . $this->admin->name . '.', 'Venue "Covered Court" was re-enabled by ' . $this->admin->name . '.'],
            \App\Models\AuditLog::query()->whereIn('action', ['venue_disabled', 'venue_enabled'])->orderBy('id')->pluck('details')->all()
        );
        $this->assertSame([$this->admin->id, $this->admin->id], \App\Models\AuditLog::query()->whereIn('action', ['venue_disabled', 'venue_enabled'])->pluck('actor_id')->all());

        $this->actingAs($this->admin)->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertSee('Venue &quot;Covered Court&quot; was disabled', false);
    }

    public function test_reassigning_tells_the_new_custodian_and_notes_the_bookings(): void
    {
        $pending = $this->booking('pending', 5, 'FER-2026-421');
        $upcoming = $this->booking('approved', 8, 'FER-2026-422');
        $newCustodian = User::factory()->create(['role' => 'custodian-venue', 'name' => 'New Custodian']);

        $this->actingAs($this->admin)->put(route('supply-office.venues.update', $this->venue), [
            'name' => 'Covered Court',
            'custodian_id' => $newCustodian->id,
            'capacity' => 300,
        ])->assertSessionHas('warning', fn (string $warning): bool => str_contains($warning, 'FER-2026-421, FER-2026-422')
            && str_contains($warning, 'The new custodian was told'));

        Notification::assertSentTo($newCustodian, VenueCustodianAssigned::class, fn ($n) => $n->bookingCount === 2);
        $history = $upcoming->histories()->where('action', 'venue_custodian_changed')->sole();
        $this->assertSame('The custodian of Covered Court changed from Old Custodian to New Custodian (by ' . $this->admin->name . ').', $history->detail);
        $this->assertTrue($pending->histories()->where('action', 'venue_custodian_changed')->exists());
    }

    public function test_a_venue_requests_refer_to_is_deactivated_not_deleted(): void
    {
        $this->booking('pending', 5, 'FER-2026-431');
        $this->actingAs($this->admin)->delete(route('supply-office.venues.destroy', $this->venue))
            ->assertSessionHasErrors(['venue' => "Covered Court can't be deleted while it has pending or upcoming bookings (FER-2026-431). Disable it instead; the bookings are kept."]);

        FacilityRequest::query()->update(['status' => 'approved', 'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->subDays(3)->toDateString()]);
        $this->actingAs($this->admin)->delete(route('supply-office.venues.destroy', $this->venue))
            ->assertSessionHasErrors(['venue' => "Covered Court can't be deleted because 1 past request(s) refer to it, and deleting it would remove it from their history and reports. Disable it instead so it can no longer be booked."]);
        $this->assertNotNull($this->venue->fresh());

        $unused = Venue::create(['name' => 'Spare Room', 'capacity' => 20, 'custodian_id' => $this->custodian->id, 'is_active' => true]);
        $this->actingAs($this->admin)->delete(route('supply-office.venues.destroy', $unused))->assertSessionHas('success', 'Venue deleted successfully.');
        $this->assertNull($unused->fresh());
    }

    public function test_venue_management_shows_the_deactivate_button_and_inactive_badge(): void
    {
        $this->venue->update(['is_active' => false]);

        $this->actingAs($this->admin)->get(route('supply-office.venues.index'))
            ->assertOk()
            ->assertSee('Re-enable')
            ->assertSee('Disabled');
    }
}
