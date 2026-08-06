<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Venue;
use App\Services\FacilityRequestRelationBackfillService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FacilityRequestRelationalBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('request_equipment');
        Schema::dropIfExists('request_venues');
        Schema::dropIfExists('facility_requests');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('equipment');

        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('equipment', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('facility_requests', function (Blueprint $table): void {
            $table->id();
            $table->json('venue')->nullable();
            $table->json('equipment')->nullable();
            $table->json('equipment_quantities')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('request_venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('request_equipment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_existing_json_data_is_migrated_correctly(): void
    {
        Venue::create(['name' => 'Main Hall']);
        Equipment::create(['name' => 'Projector']);

        $request = FacilityRequest::withoutEvents(function (): FacilityRequest {
            return FacilityRequest::create([
                'venue' => ['Main Hall', 'Missing Hall'],
                'equipment' => ['Projector', 'Missing Tool'],
                'equipment_quantities' => ['Projector' => 4],
            ]);
        });

        $summary = app(FacilityRequestRelationBackfillService::class)->run();

        $this->assertSame(1, $summary['request_venues_created']);
        $this->assertSame(1, $summary['request_equipment_created']);
        $this->assertSame(1, $request->requestVenues()->count());
        $this->assertSame(1, $request->requestEquipment()->count());
        $this->assertSame('Main Hall', $request->requestVenues()->first()->name);
        $this->assertSame('Projector', $request->requestEquipment()->first()->name);
        $this->assertSame(4, $request->requestEquipment()->first()->quantity);
    }

    public function test_running_the_backfill_twice_does_not_create_duplicates(): void
    {
        Venue::create(['name' => 'Main Hall']);
        Equipment::create(['name' => 'Projector']);

        $request = FacilityRequest::withoutEvents(function (): FacilityRequest {
            return FacilityRequest::create([
                'venue' => ['Main Hall'],
                'equipment' => ['Projector'],
                'equipment_quantities' => ['Projector' => 2],
            ]);
        });

        app(FacilityRequestRelationBackfillService::class)->run();
        $summary = app(FacilityRequestRelationBackfillService::class)->run();

        $this->assertSame(1, $request->requestVenues()->count());
        $this->assertSame(1, $request->requestEquipment()->count());
        $this->assertSame(0, $summary['request_venues_created']);
        $this->assertSame(0, $summary['request_equipment_created']);
    }

    public function test_missing_equipment_quantities_default_to_one(): void
    {
        Venue::create(['name' => 'Main Hall']);
        Equipment::create(['name' => 'Projector']);

        $request = FacilityRequest::withoutEvents(function (): FacilityRequest {
            return FacilityRequest::create([
                'venue' => ['Main Hall'],
                'equipment' => ['Projector'],
                'equipment_quantities' => null,
            ]);
        });

        app(FacilityRequestRelationBackfillService::class)->run();

        $this->assertSame(1, $request->requestEquipment()->first()->quantity);
    }

    public function test_venue_relationships_are_linked_to_the_original_request(): void
    {
        Venue::create(['name' => 'Main Hall']);
        Equipment::create(['name' => 'Projector']);

        $request = FacilityRequest::withoutEvents(function (): FacilityRequest {
            return FacilityRequest::create([
                'venue' => ['Main Hall'],
                'equipment' => ['Projector'],
                'equipment_quantities' => ['Projector' => 3],
            ]);
        });

        app(FacilityRequestRelationBackfillService::class)->run();

        $venueRecord = $request->requestVenues()->first();
        $equipmentRecord = $request->requestEquipment()->first();

        $this->assertNotNull($venueRecord);
        $this->assertSame($request->id, $venueRecord->facility_request_id);
        $this->assertNotNull($equipmentRecord);
        $this->assertSame($request->id, $equipmentRecord->facility_request_id);
    }
}
