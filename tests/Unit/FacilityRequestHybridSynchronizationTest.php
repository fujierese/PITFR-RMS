<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\RequestEquipment;
use App\Models\RequestVenue;
use Tests\TestCase;

class FacilityRequestHybridSynchronizationTest extends TestCase
{
    public function test_helper_methods_prefer_relational_data_when_present(): void
    {
        $request = new FacilityRequest([
            'venue' => ['Legacy Hall'],
            'equipment' => ['Legacy Mic'],
            'equipment_quantities' => ['Legacy Mic' => 5],
        ]);

        $venue = new RequestVenue(['name' => 'New Hall']);
        $venue->forceFill(['id' => 10, 'name' => 'New Hall']);

        $equipment = new RequestEquipment(['name' => 'New Mic', 'quantity' => 3]);
        $equipment->forceFill(['id' => 20, 'name' => 'New Mic', 'quantity' => 3]);

        $request->setRelation('requestVenues', collect([$venue]));
        $request->setRelation('requestEquipment', collect([$equipment]));

        $this->assertSame(['New Hall'], $request->getVenueNames());
        $this->assertSame(['New Mic'], $request->getEquipmentItems());
        $this->assertSame(['New Mic' => 3], $request->getEquipmentQuantities());
        $this->assertSame([10], $request->getVenueIds());
        $this->assertSame([20], $request->getEquipmentIds());
    }

    public function test_helper_methods_fall_back_to_json_columns_when_relations_are_missing(): void
    {
        $request = new FacilityRequest([
            'venue' => ['Legacy Hall'],
            'equipment' => ['Legacy Mic'],
            'equipment_quantities' => ['Legacy Mic' => 5],
        ]);

        $this->assertSame(['Legacy Hall'], $request->getVenueNames());
        $this->assertSame(['Legacy Mic'], $request->getEquipmentItems());
        $this->assertSame(['Legacy Mic' => 5], $request->getEquipmentQuantities());
        $this->assertSame([], $request->getVenueIds());
        $this->assertSame([], $request->getEquipmentIds());
    }
}
