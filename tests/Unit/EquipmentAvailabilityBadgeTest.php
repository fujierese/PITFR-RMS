<?php

namespace Tests\Unit;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentAvailabilityBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected User $custodian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->custodian = User::factory()->create(['role' => 'custodian']);
    }

    public function test_availability_badge_class_is_green_when_fully_available()
    {
        $equipment = Equipment::factory()->create([
            'custodian_id' => $this->custodian->id,
            'quantity' => 5,
            'quantity_available' => 5,
        ]);

        $this->assertEquals('bg-green-100 text-green-700', $equipment->availabilityBadgeClass());
    }

    public function test_availability_badge_class_is_yellow_when_partially_available()
    {
        $equipment = Equipment::factory()->create([
            'custodian_id' => $this->custodian->id,
            'quantity' => 5,
            'quantity_available' => 2,
        ]);

        $this->assertEquals('bg-yellow-100 text-yellow-700', $equipment->availabilityBadgeClass());
    }

    public function test_availability_badge_class_is_red_when_not_available()
    {
        $equipment = Equipment::factory()->create([
            'custodian_id' => $this->custodian->id,
            'quantity' => 5,
            'quantity_available' => 0,
        ]);

        $this->assertEquals('bg-red-100 text-red-700', $equipment->availabilityBadgeClass());
    }
}
