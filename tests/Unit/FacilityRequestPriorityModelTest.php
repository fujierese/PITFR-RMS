<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityRequestPriorityModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_and_urgency_represent_all_four_combinations(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $combinations = [
            ['priority' => 'institutional', 'is_emergency' => false],
            ['priority' => 'regular', 'is_emergency' => true],
            ['priority' => 'institutional', 'is_emergency' => true],
            ['priority' => 'regular', 'is_emergency' => false],
        ];

        foreach ($combinations as $index => $combination) {
            $request = FacilityRequest::create(array_merge($this->baseAttributes($requestor, $index), $combination));

            $this->assertSame($combination['priority'], $request->priority);
            $this->assertSame($combination['is_emergency'], $request->is_emergency);
        }
    }

    public function test_requestor_metadata_is_separate_from_final_classification(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);
        $request = FacilityRequest::create(array_merge($this->baseAttributes($requestor), [
            'priority' => 'regular',
            'requested_priority' => 'institutional',
            'is_emergency' => false,
            'requested_is_emergency' => true,
            'emergency_justification' => 'A time-sensitive institutional activity requires review.',
        ]));

        $this->assertSame('regular', $request->priority);
        $this->assertSame('institutional', $request->requested_priority);
        $this->assertFalse($request->is_emergency);
        $this->assertTrue($request->requested_is_emergency);
        $this->assertSame('A time-sensitive institutional activity requires review.', $request->emergency_justification);
    }

    private function baseAttributes(User $requestor, int $index = 0): array
    {
        return [
            'control_number' => 'FER-PRIORITY-' . uniqid('', true) . '-' . $index,
            'date_requested' => '2026-08-28',
            'department' => 'Test Department',
            'name_of_activity' => 'Priority Test Activity',
            'expected_participants' => 10,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => [],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ];
    }
}
