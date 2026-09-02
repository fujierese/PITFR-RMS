<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\UserSeeder;
use Database\Seeders\VenueAndEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_seeders_are_idempotent_and_apply_confirmed_values(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(VenueAndEquipmentSeeder::class);
        $this->seed(VenueAndEquipmentSeeder::class);

        $this->assertSame(1, Venue::where('name', 'Gymnasium')->count());
        $this->assertSame(1000, Venue::where('name', 'Gymnasium')->value('capacity'));
        $this->assertSame(50, Venue::whereIn('name', ['Balay Alumni', 'Balay Alumni Hall'])->value('capacity'));

        foreach ([
            'Sound System' => 1,
            'Wireless Microphones' => 1,
            'Non-Wireless Microphones' => 1,
            'Iwata Cooler Fans' => 4,
            'Industrial Fans' => 6,
            'Tables' => 10,
            'Canopies' => 10,
            'Monobloc Chairs' => 600,
        ] as $name => $quantity) {
            $this->assertSame(1, Equipment::where('name', $name)->count());
            $this->assertSame($quantity, Equipment::where('name', $name)->value('quantity'));
            $this->assertSame($quantity, Equipment::where('name', $name)->value('quantity_available'));
        }

        $this->assertSame(
            User::where('username', 'rguillemer@gmail.com')->value('id'),
            Equipment::where('name', 'Sound System')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'lalmerino@gmail.com')->value('id'),
            Equipment::where('name', 'Iwata Cooler Fans')->value('custodian_id'),
        );
        $this->assertContains(
            User::where('username', 'jrvillas@gmail.com')->value('id'),
            Equipment::where('name', 'Iwata Cooler Fans')->first()->getAuthorizedCustodianIds(),
        );
        $this->assertSame(
            User::where('username', 'jsuralta@gmail.com')->value('id'),
            Equipment::where('name', 'Canopies')->value('custodian_id'),
        );
    }

    public function test_reference_seeders_preserve_unrelated_existing_records(): void
    {
        $unrelatedUser = User::factory()->create(['username' => 'unrelated-reference-user']);
        $unconfirmedVenue = Venue::create([
            'name' => 'Conference Hall & Interaction Center (CHIC)',
            'capacity' => 777,
            'custodian_id' => $unrelatedUser->id,
        ]);
        $request = FacilityRequest::factory()->create(['requested_by_id' => $unrelatedUser->id]);
        $history = $request->addHistory('created', 'Unrelated record', $unrelatedUser->id);

        $this->seed(UserSeeder::class);
        $this->seed(VenueAndEquipmentSeeder::class);

        $this->assertDatabaseHas('users', ['id' => $unrelatedUser->id, 'username' => 'unrelated-reference-user']);
        $this->assertDatabaseHas('facility_requests', ['id' => $request->id]);
        $this->assertDatabaseHas('request_histories', ['id' => $history->id, 'facility_request_id' => $request->id]);
        $this->assertDatabaseHas('venues', ['id' => $unconfirmedVenue->id, 'capacity' => 777]);
    }
}