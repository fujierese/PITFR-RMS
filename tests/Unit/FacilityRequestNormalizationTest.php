<?php

namespace Tests\Unit;

use App\Models\FacilityRequest;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FacilityRequestNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('reservation_schedules');
        Schema::dropIfExists('request_status_history');
        Schema::dropIfExists('request_equipment');
        Schema::dropIfExists('request_venues');
        Schema::dropIfExists('facility_requests');

        Schema::create('facility_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('control_number')->nullable();
            $table->date('date_requested')->nullable();
            $table->string('department')->nullable();
            $table->string('name_of_activity')->nullable();
            $table->unsignedInteger('expected_participants')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->json('venue')->nullable();
            $table->json('equipment')->nullable();
            $table->json('equipment_quantities')->nullable();
            $table->string('other_venue')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('venue_status')->default('pending');
            $table->string('equipment_status')->default('pending');
            $table->string('priority')->default('regular');
            $table->boolean('is_emergency')->default(false);
            $table->string('proposal_file')->nullable();
            $table->timestamps();
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

        Schema::create('request_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->string('status');
            $table->string('detail')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_request_id')->constrained('facility_requests')->cascadeOnDelete();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_relational_request_items_are_synced_from_request_payload(): void
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-001',
            'date_requested' => now()->toDateString(),
            'department' => 'ICT',
            'name_of_activity' => 'Campus Orientation',
            'expected_participants' => 40,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => ['Sound System', 'Microphones'],
            'equipment_quantities' => ['Sound System' => 2, 'Microphones' => 3],
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $request->syncRelationalItems();

        $this->assertSame(['Conference Hall & Interaction Center (CHIC)'], $request->fresh()->requestVenues()->pluck('name')->all());
        $this->assertSame(['Sound System', 'Microphones'], $request->fresh()->requestEquipment()->pluck('name')->all());
        $this->assertSame([2, 3], $request->fresh()->requestEquipment()->pluck('quantity')->all());
    }

    public function test_normalize_schedule_range_treats_end_time_as_next_day_for_overnight_booking(): void
    {
        $range = FacilityRequest::normalizeScheduleRange('2026-08-11', '17:00', '2026-08-11', '00:00');

        $this->assertSame('2026-08-11 17:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-12 00:00:00', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_normalize_schedule_range_keeps_same_day_for_non_overnight_booking(): void
    {
        $range = FacilityRequest::normalizeScheduleRange('2026-08-11', '08:00', '2026-08-11', '12:00');

        $this->assertSame('2026-08-11 08:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-11 12:00:00', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_specific_time_duration_remains_flexible(): void
    {
        $range = FacilityRequest::resolveReservationDuration('specific_time', '2026-08-20', '13:00', '2026-08-22', '17:00');

        $this->assertSame('2026-08-20 13:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-22 17:00:00', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_whole_day_duration_uses_8_am_to_1159_pm(): void
    {
        $range = FacilityRequest::resolveReservationDuration('whole_day', '2026-08-20', '00:00', '2026-08-20', '23:59');

        $this->assertSame('2026-08-20 08:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-20 23:59:00', $range['end']->format('Y-m-d H:i:s'));
        $this->assertSame('08:00', $range['start']->format('H:i'));
        $this->assertSame('23:59', $range['end']->format('H:i'));
    }

    public function test_consecutive_whole_day_dates_preserve_range_boundaries(): void
    {
        $range = FacilityRequest::resolveReservationDuration('whole_day', '2026-08-20', '00:00', '2026-08-22', '23:59');

        $this->assertSame('2026-08-20 08:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-22 23:59:00', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_specific_time_consecutive_dates_preserve_existing_behavior(): void
    {
        $range = FacilityRequest::resolveReservationDuration('specific_time', '2026-08-20', '13:00', '2026-08-22', '17:00');

        $this->assertSame('2026-08-20 13:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-22 17:00:00', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_whole_day_duration_remains_on_the_selected_date(): void
    {
        $range = FacilityRequest::resolveReservationDuration('whole_day', '2026-08-21', '00:00', '2026-08-21', '23:59');

        $this->assertSame('2026-08-21 08:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-21 23:59:00', $range['end']->format('Y-m-d H:i:s'));
        $this->assertSame('08:00', $range['start']->format('H:i'));
        $this->assertSame('23:59', $range['end']->format('H:i'));
    }

    public function test_overlapping_schedule_uses_full_datetime_range_for_overnight_requests(): void
    {
        $first = FacilityRequest::create([
            'control_number' => 'FER-2026-010',
            'date_requested' => now()->toDateString(),
            'department' => 'ICT',
            'name_of_activity' => 'First session',
            'expected_participants' => 20,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'start_time' => '22:00',
            'end_time' => '02:00',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $first->syncRelationalItems();

        $second = FacilityRequest::create([
            'control_number' => 'FER-2026-011',
            'date_requested' => now()->toDateString(),
            'department' => 'ICT',
            'name_of_activity' => 'Second session',
            'expected_participants' => 20,
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-02',
            'start_time' => '00:30',
            'end_time' => '01:30',
            'venue' => ['Conference Hall & Interaction Center (CHIC)'],
            'equipment' => [],
            'equipment_quantities' => [],
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);

        $second->syncRelationalItems();

        $this->assertTrue($first->overlapsRequest($second));
        $this->assertTrue($second->overlapsRequest($first));

        $firstStart = $first->getRequestedStartDateTime();
        $firstEnd = $first->getRequestedEndDateTime();
        $secondStart = $second->getRequestedStartDateTime();
        $secondEnd = $second->getRequestedEndDateTime();

        $this->assertTrue($firstStart->lt($secondEnd));
        $this->assertTrue($secondStart->lt($firstEnd));
        $this->assertSame('2026-08-01 22:00:00', $firstStart->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-02 02:00:00', $firstEnd->format('Y-m-d H:i:s'));
    }
}
