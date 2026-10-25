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
        Equipment::where('name', 'Wireless Microphones')->update(['quantity_available' => 2]);
        $this->seed(VenueAndEquipmentSeeder::class);

        $this->assertSame(1, Venue::where('name', 'Gymnasium')->count());
        $this->assertSame(1000, Venue::where('name', 'Gymnasium')->value('capacity'));
        $this->assertSame(200, Venue::where('name', 'Conference Hall & Interaction Center (CHIC)')->value('capacity'));
        $this->assertSame(50, Venue::whereIn('name', ['Balay Alumni', 'Balay Alumni Hall'])->value('capacity'));
        $this->assertSame('Leogen Almerino', User::where('username', 'lalmerino@gmail.com')->value('name'));
        $this->assertSame('Ritchard Villas', User::where('username', 'jrvillas@gmail.com')->value('name'));

        foreach ([
            'Sound System' => 3,
            'Wireless Microphones' => 3,
            'Non-Wireless Microphones' => 3,
            'Iwata Cooler Fans' => 4,
            'Industrial Fans' => 6,
            'Tables' => 10,
            'Chairs' => 50,
            'Aircon' => 1,
            'Canopies' => 10,
            'Monobloc Chairs' => 600,
        ] as $name => $quantity) {
            $this->assertSame(1, Equipment::where('name', $name)->count());
            $this->assertSame($quantity, Equipment::where('name', $name)->value('quantity'));
            $expectedAvailable = $name === 'Wireless Microphones' ? 2 : $quantity;
            $this->assertSame($expectedAvailable, Equipment::where('name', $name)->value('quantity_available'));
        }

        $this->assertSame(
            User::where('username', 'rguillemer@gmail.com')->value('id'),
            Equipment::where('name', 'Sound System')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'lalmerino@gmail.com')->value('id'),
            Equipment::where('name', 'Iwata Cooler Fans')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'lalmerino@gmail.com')->value('id'),
            Equipment::where('name', 'Aircon')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'mmercado@gmail.com')->value('id'),
            Equipment::where('name', 'Chairs')->value('custodian_id'),
        );
        $this->assertContains(
            User::where('username', 'jrvillas@gmail.com')->value('id'),
            Equipment::where('name', 'Iwata Cooler Fans')->first()->getAuthorizedCustodianIds(),
        );
        $this->assertContains('Monobloc Chairs', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Balay Alumni'));
        $this->assertSame([
            'Sound System',
            'Aircon',
            'Chairs',
        ], \App\Services\VenueEquipmentPolicy::getDefaultEquipment('Balay Alumni'));
        $this->assertSame([
            'Sound System',
            'Iwata Cooler Fans',
            'Podium',
            'HDMI',
            'Television (CHIC)',
        ], \App\Services\VenueEquipmentPolicy::getDefaultEquipment('Conference Hall & Interaction Center (CHIC)'));
        $this->assertSame([
            'Balay Alumni',
            'Conference Hall & Interaction Center (CHIC)',
        ], \App\Services\VenueEquipmentPolicy::getVenuesWithDefaultEquipment());
        $this->assertSame([], \App\Services\VenueEquipmentPolicy::getDefaultEquipment('Gymnasium'));
        $this->assertSame(['Balay Alumni'], \App\Services\VenueEquipmentPolicy::getVenueRestrictedEquipment()['Aircon']);
        $this->assertNotContains('Wireless Microphones', \App\Services\VenueEquipmentPolicy::getDefaultEquipment('Balay Alumni'));
        $this->assertNotContains('Non-Wireless Microphones', \App\Services\VenueEquipmentPolicy::getDefaultEquipment('Conference Hall & Interaction Center (CHIC)'));
        $this->assertNotContains('Aircon', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Balay Alumni'));
        $this->assertContains('Aircon', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Conference Hall & Interaction Center (CHIC)'));
        $this->assertContains('Aircon', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Gymnasium'));
        $this->assertNotContains('Iwata Cooler Fans', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Balay Alumni'));
        $this->assertNotContains('Iwata Cooler Fans', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Bahay Alumni'));
        $this->assertNotContains('Iwata Cooler Fans', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Conference Hall & Interaction Center (CHIC)'));
        $this->assertContains('Iwata Cooler Fans', \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Gymnasium'));
        $this->assertSame(
            User::where('username', 'jsuralta@gmail.com')->value('id'),
            Equipment::where('name', 'Canopies')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'rguillemer@gmail.com')->value('id'),
            Equipment::where('name', 'Lights')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'asala@gmail.com')->value('id'),
            Equipment::where('name', 'Podium')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'asala@gmail.com')->value('id'),
            Equipment::where('name', 'HDMI')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'asala@gmail.com')->value('id'),
            Equipment::where('name', 'Television (CHIC)')->value('custodian_id'),
        );
        $this->assertSame(
            User::where('username', 'mmercado@gmail.com')->value('id'),
            Equipment::where('name', 'Television (Balay Alumni)')->value('custodian_id'),
        );
        $this->assertSame(1, Equipment::where('name', 'Television (CHIC)')->value('quantity_available'));
        $this->assertSame(0, Equipment::where('name', 'Television (Balay Alumni)')->value('quantity_available'));
        $this->assertFalse((bool) Equipment::where('name', 'Television (Balay Alumni)')->value('is_active'));
        $this->assertNotContains(
            'Television (Balay Alumni)',
            \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Balay Alumni'),
        );
        $this->assertNotContains(
            'Television (CHIC)',
            \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Balay Alumni'),
        );
        $this->assertNotContains(
            'Television (CHIC)',
            \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Gymnasium'),
        );
        $this->assertContains(
            'Television (Balay Alumni)',
            \App\Services\VenueEquipmentPolicy::getIncompatibleEquipment('Conference Hall & Interaction Center (CHIC)'),
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