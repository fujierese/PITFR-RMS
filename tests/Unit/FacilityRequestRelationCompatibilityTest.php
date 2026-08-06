<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use App\Models\RequestVenue;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FacilityRequestRelationCompatibilityTest extends TestCase
{
    public function test_stage_approver_name_prefers_relationship_when_available(): void
    {
        Schema::shouldReceive('hasColumn')->andReturn(true);

        $request = new FacilityRequest();
        $request->forceFill([
            'approved_by' => 'Legacy Approver',
            'approved_by_id' => 42,
        ]);

        $request->setRelation('approvedBy', new User(['id' => 42, 'name' => 'Jane Doe']));

        $this->assertSame('Jane Doe', $request->getStageApproverName('final'));
    }

    public function test_venue_names_use_related_venue_names_when_present(): void
    {
        Schema::shouldReceive('hasTable')->andReturn(true);

        $request = new FacilityRequest();
        $request->forceFill(['venue' => []]);

        $venue = new Venue(['id' => 7, 'name' => 'Main Hall']);
        $requestVenue = new RequestVenue(['id' => 1, 'venue_id' => 7, 'name' => '']);
        $requestVenue->setRelation('venue', $venue);

        $request->setRelation('requestVenues', collect([$requestVenue]));

        $this->assertSame(['Main Hall'], $request->getVenueNames());
    }

    public function test_request_venue_resolved_name_falls_back_to_stored_name_when_relation_is_not_loaded(): void
    {
        $requestVenue = new RequestVenue(['id' => 2, 'venue_id' => 8, 'name' => 'Conference Hall']);

        $this->assertSame('Conference Hall', $requestVenue->resolvedName());
    }
}
