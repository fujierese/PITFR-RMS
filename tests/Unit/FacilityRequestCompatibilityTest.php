<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class FacilityRequestCompatibilityTest extends TestCase
{
    public function test_legacy_facility_request_attributes_resolve_to_the_new_schema_names(): void
    {
        $request = new FacilityRequest();
        $request->setAttribute('start_date', Carbon::parse('2026-07-30'));
        $request->setAttribute('end_date', Carbon::parse('2026-08-01'));
        $request->setAttribute('start_time', '14:30');

        $requester = new User();
        $requester->setAttribute('name', 'Jane Doe');
        $requester->setAttribute('position', 'Coordinator');
        $request->setRelation('requester', $requester);

        $this->assertSame('2026-07-30', $request->requesting_date->toDateString());
        $this->assertSame('2026-08-01', $request->requesting_end_date->toDateString());
        $this->assertSame('14:30', $request->time);
        $this->assertSame('Jane Doe', $request->requested_by);
        $this->assertSame('Coordinator', $request->requested_by_position);
        $this->assertNull($request->other_equipment);
    }
}
