<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_venue_custodian_can_manage_only_owned_venues(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $otherCustodian = User::factory()->create(['role' => 'custodian-venue']);
        $venue = Venue::create(['name' => 'Hall A', 'custodian_id' => $custodian->id, 'capacity' => 20]);
        $otherVenue = Venue::create(['name' => 'Hall B', 'custodian_id' => $otherCustodian->id, 'capacity' => 20]);

        $this->actingAs($custodian)->put(route('custodian.venues.update', $venue), [
            'name' => 'Hall A Updated',
            'capacity' => 25,
        ])->assertRedirect();

        $this->actingAs($custodian)->put(route('custodian.venues.update', $otherVenue), [
            'name' => 'Not Allowed',
            'capacity' => 25,
        ])->assertForbidden();

        $this->assertSame('Hall A Updated', $venue->fresh()->name);
        $this->assertSame('Hall B', $otherVenue->fresh()->name);
    }

    public function test_equipment_custodian_can_set_quantity_and_disable_owned_equipment(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        $equipment = Equipment::create([
            'name' => 'Projector',
            'custodian_id' => $custodian->id,
            'quantity' => 4,
            'quantity_available' => 4,
        ]);

        $this->actingAs($custodian)->put(route('custodian.equipment.update', $equipment), [
            'name' => 'Projector',
            'quantity' => 6,
            'quantity_available' => 3,
        ])->assertRedirect();

        $this->actingAs($custodian)->patch(route('custodian.equipment.toggle', $equipment))->assertRedirect();

        $equipment->refresh();
        $this->assertSame(6, $equipment->quantity);
        $this->assertSame(3, $equipment->quantity_available);
        $this->assertFalse($equipment->is_active);
        $this->assertFalse(app(AvailabilityService::class)->checkEquipmentAvailability('Projector', 1)['available']);
    }

    public function test_supply_office_has_admin_user_access_but_not_custodian_resource_routes(): void
    {
        $supplyOffice = User::factory()->create(['role' => 'supply_office']);

        $this->actingAs($supplyOffice)
            ->post('/supply-office/venues', ['name' => 'Unauthorized', 'capacity' => 10])
            ->assertNotFound();

        $this->actingAs($supplyOffice)
            ->get(route('supply-office.users'))
            ->assertOk();
    }
}
