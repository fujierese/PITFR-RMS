<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PerVenueSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_submission_persists_a_separate_schedule_for_each_venue(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $this->createTestVenues();

        $firstDate = now()->addDays(5)->toDateString();
        $secondDate = now()->addDays(8)->toDateString();

        $response = $this->actingAs($requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Separate Venue Schedules',
            'purpose' => 'Test venue-specific dates',
            'expected_participants' => 20,
            'venue' => ['Test Hall', 'Test Court'],
            'venue_schedules' => [
                'Test Hall' => [
                    'start_date' => $firstDate,
                    'end_date' => $firstDate,
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'reservation_duration' => 'specific_time',
                ],
                'Test Court' => [
                    'start_date' => $secondDate,
                    'end_date' => $secondDate,
                    'start_time' => '13:00',
                    'end_time' => '17:00',
                    'reservation_duration' => 'specific_time',
                ],
            ],
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors();

        $request = FacilityRequest::query()
            ->where('name_of_activity', 'Separate Venue Schedules')
            ->firstOrFail();

        $schedules = $request->requestVenues()->get()->keyBy('name');
        $this->assertSame($firstDate, $schedules['Test Hall']->start_date->toDateString());
        $this->assertSame('08:00', $schedules['Test Hall']->start_time);
        $this->assertSame('10:00', $schedules['Test Hall']->end_time);
        $this->assertSame($secondDate, $schedules['Test Court']->start_date->toDateString());
        $this->assertSame('13:00', $schedules['Test Court']->start_time);
        $this->assertSame('17:00', $schedules['Test Court']->end_time);
        $summaries = collect($request->getVenueScheduleSummaries())->keyBy('venue');
        $this->assertSame('8:00 AM – 10:00 AM', $summaries['Test Hall']['time']);
        $this->assertSame('1:00 PM – 5:00 PM', $summaries['Test Court']['time']);
    }

    public function test_multi_day_schedule_summary_counts_daily_reservation_hours_and_excludes_whole_day_lunch(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $this->createTestVenues();
        $startDate = now()->addDays(5)->toDateString();
        $endDate = now()->addDays(6)->toDateString();

        $request = FacilityRequest::create([
            'control_number' => 'TEST-MULTI-DAY-DURATION',
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'Multi-day daytime schedule',
            'expected_participants' => 10,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'reservation_duration' => 'whole_day',
            'venue' => ['Test Hall'],
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);

        $request->syncRelationalItems([
            'Test Hall' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '08:00',
                'end_time' => '17:00',
                'reservation_duration' => 'whole_day',
            ],
        ]);

        $summary = $request->fresh()->getVenueScheduleSummaries()[0];
        $this->assertSame('8:00 AM – 5:00 PM', $summary['time']);
        $this->assertSame('16 hours', $summary['duration']);

        $request->syncRelationalItems([
            'Test Hall' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '08:00',
                'end_time' => '17:00',
                'reservation_duration' => 'specific_time',
            ],
        ]);
        $this->assertSame('18 hours', $request->fresh()->getVenueScheduleSummaries()[0]['duration']);

        $request->syncRelationalItems([
            'Test Hall' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '16:00',
                'end_time' => '01:00',
                'reservation_duration' => 'specific_time',
            ],
        ]);
        $invalidSummary = $request->fresh()->getVenueScheduleSummaries()[0];
        $this->assertSame('Schedule needs review', $invalidSummary['time']);
        $this->assertSame('Schedule needs review', $invalidSummary['duration']);
    }

    public function test_equipment_is_saved_against_each_selected_venue(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $this->createTestVenues();
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 3,
            'quantity_available' => 3,
        ]);
        $date = now()->addDays(5)->toDateString();

        $this->actingAs($requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Venue Equipment Mapping',
            'expected_participants' => 20,
            'venue' => ['Test Hall', 'Test Court'],
            'venue_schedules' => [
                'Test Hall' => [
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'reservation_duration' => 'specific_time',
                ],
                'Test Court' => [
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'reservation_duration' => 'specific_time',
                ],
            ],
            'venue_equipment' => [
                'Test Hall' => ['Sound System'],
                'Test Court' => ['Sound System'],
            ],
            'venue_equipment_quantities' => [
                'Test Hall' => ['Sound System' => 1],
                'Test Court' => ['Sound System' => 1],
            ],
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = FacilityRequest::query()
            ->where('name_of_activity', 'Venue Equipment Mapping')
            ->with('requestVenues')
            ->firstOrFail();
        $allocations = $request->requestEquipment()
            ->with('requestVenue')
            ->get()
            ->mapWithKeys(fn ($item): array => [$item->requestVenue->resolvedName() => $item->quantity])
            ->all();

        $this->assertSame(['Test Hall' => 1, 'Test Court' => 1], $allocations);
        $this->assertSame(['Sound System' => 2], $request->getEquipmentQuantities());
        $this->assertSame([
            'Test Hall' => ['Sound System' => 1],
            'Test Court' => ['Sound System' => 1],
        ], $request->getVenueEquipmentAllocations());
        $this->assertSame([
            'Test Hall: Sound System (1)',
            'Test Court: Sound System (1)',
        ], $request->getVenueEquipmentLabels());
        $request->update(['venue' => ['Test Court']]);
        $request->syncRelationalItems();

        $this->assertSame([
            'Test Court' => ['Sound System' => 2],
        ], $request->fresh()->getVenueEquipmentAllocations());
    }

    public function test_overlapping_equipment_allocations_across_venues_cannot_exceed_shared_stock(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $this->createTestVenues();
        $custodian = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::factory()->create([
            'name' => 'Sound System',
            'custodian_id' => $custodian->id,
            'quantity' => 3,
            'quantity_available' => 3,
        ]);
        $date = now()->addDays(5)->toDateString();

        $this->actingAs($requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Overbooked Venue Equipment',
            'expected_participants' => 20,
            'venue' => ['Test Hall', 'Test Court'],
            'venue_schedules' => [
                'Test Hall' => [
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'reservation_duration' => 'specific_time',
                ],
                'Test Court' => [
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'reservation_duration' => 'specific_time',
                ],
            ],
            'venue_equipment' => [
                'Test Hall' => ['Sound System'],
                'Test Court' => ['Sound System'],
            ],
            'venue_equipment_quantities' => [
                'Test Hall' => ['Sound System' => 2],
                'Test Court' => ['Sound System' => 2],
            ],
            'e_signature_file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasErrors('equipment');

        $this->assertDatabaseMissing('facility_requests', [
            'name_of_activity' => 'Overbooked Venue Equipment',
        ]);
    }

    public function test_over_capacity_venue_is_blocked_unless_an_exception_is_requested(): void
    {
        $requestor = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
        ]);
        $this->createTestVenues();
        Venue::where('name', 'Test Hall')->update(['capacity' => 50]);

        $date = now()->addDays(5)->toDateString();
        $payload = [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Over Capacity Booking',
            'purpose' => 'Capacity rule test',
            'expected_participants' => 60,
            'venue' => ['Test Hall', 'Test Court'],
            'venue_schedules' => [
                'Test Hall' => ['start_date' => $date, 'end_date' => $date, 'start_time' => '08:00', 'end_time' => '10:00', 'reservation_duration' => 'specific_time'],
                'Test Court' => ['start_date' => $date, 'end_date' => $date, 'start_time' => '08:00', 'end_time' => '10:00', 'reservation_duration' => 'specific_time'],
            ],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ];

        // D2: a venue smaller than its participant count is blocked.
        $this->actingAs($requestor)->post(route('requestor.store'), $payload)
            ->assertSessionHasErrors('venue_participants.Test Hall');
        $this->assertDatabaseMissing('facility_requests', ['name_of_activity' => 'Over Capacity Booking']);

        // With a reason it goes through as a capacity exception for the Supply Office.
        $payload['capacity_exceptions'] = ['Test Hall' => 'Seated in two shifts.'];
        $payload['activity_proposal_file'] = UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf');
        $this->actingAs($requestor)->post(route('requestor.store'), $payload)
            ->assertSessionHas('warning', fn (string $warning): bool => str_contains($warning, 'Capacity exception requested for Test Hall')
                && ! str_contains($warning, 'Test Court'));
        $this->assertDatabaseHas('facility_requests', [
            'requested_by_id' => $requestor->id,
            'name_of_activity' => 'Over Capacity Booking',
            'capacity_exception_status' => 'pending',
        ]);
    }

    public function test_availability_checks_each_venue_schedule_and_equipment_time_window(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->createTestVenues();
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Equipment::factory()->create([
            'name' => 'Test Projector',
            'custodian_id' => $equipmentCustodian->id,
            'quantity' => 1,
            'quantity_available' => 1,
        ]);

        $date = now()->addDays(5)->toDateString();
        $existing = FacilityRequest::create([
            'control_number' => 'TEST-PER-VENUE-EXISTING',
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'Existing Equipment Booking',
            'expected_participants' => 10,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'venue' => ['Test Hall'],
            'equipment' => ['Test Projector'],
            'equipment_quantities' => ['Test Projector' => 1],
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $existing->syncRelationalItems([
            'Test Hall' => [
                'start_date' => $date,
                'end_date' => $date,
                'start_time' => '10:00',
                'end_time' => '11:00',
                'reservation_duration' => 'specific_time',
            ],
        ]);

        $candidate = FacilityRequest::create([
            'control_number' => 'TEST-PER-VENUE-CANDIDATE',
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'Separated Equipment Windows',
            'expected_participants' => 10,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '08:00',
            'end_time' => '13:00',
            'venue' => ['Test Hall', 'Test Court'],
            'equipment' => ['Test Projector'],
            'equipment_quantities' => ['Test Projector' => 1],
            'requested_by_id' => $requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $candidate->syncRelationalItems([
            'Test Hall' => [
                'start_date' => $date,
                'end_date' => $date,
                'start_time' => '08:00',
                'end_time' => '09:00',
                'reservation_duration' => 'specific_time',
            ],
            'Test Court' => [
                'start_date' => $date,
                'end_date' => $date,
                'start_time' => '12:00',
                'end_time' => '13:00',
                'reservation_duration' => 'specific_time',
            ],
        ]);

        $this->assertNull(app(AvailabilityService::class)->checkFacilityRequest($candidate));
    }

    public function test_calendar_emits_each_venue_at_its_own_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->createTestVenues();

        $firstDate = now()->addDays(5)->toDateString();
        $secondDate = now()->addDays(8)->toDateString();
        $request = FacilityRequest::create([
            'control_number' => 'TEST-PER-VENUE-CALENDAR',
            'date_requested' => now()->toDateString(),
            'department' => 'Test Department',
            'name_of_activity' => 'Venue Calendar Schedule',
            'expected_participants' => 10,
            'start_date' => $firstDate,
            'end_date' => $secondDate,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'venue' => ['Test Hall', 'Test Court'],
            'equipment' => [],
            'equipment_quantities' => [],
            'requested_by_id' => $requestor->id,
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
        ]);
        $request->syncRelationalItems([
            'Test Hall' => [
                'start_date' => $firstDate,
                'end_date' => $firstDate,
                'start_time' => '08:00',
                'end_time' => '10:00',
                'reservation_duration' => 'specific_time',
            ],
            'Test Court' => [
                'start_date' => $secondDate,
                'end_date' => $secondDate,
                'start_time' => '13:00',
                'end_time' => '17:00',
                'reservation_duration' => 'specific_time',
            ],
        ]);

        $response = $this->actingAs($admin)->getJson(route('calendar.events'));
        $response->assertOk();

        $events = collect($response->json())
            ->where('extendedProps.facilityRequestId', $request->id)
            ->keyBy('venue');

        $this->assertCount(2, $events);
        $this->assertSame($firstDate . 'T08:00:00', $events['Test Hall']['start']);
        $this->assertSame($firstDate . 'T10:00:00', $events['Test Hall']['end']);
        $this->assertSame($secondDate . 'T13:00:00', $events['Test Court']['start']);
        $this->assertSame($secondDate . 'T17:00:00', $events['Test Court']['end']);

        $filteredResponse = $this->actingAs($admin)->getJson(route('calendar.events', ['venue' => 'Test Court']));
        $filteredResponse->assertOk()->assertJsonCount(1);
        $this->assertSame('Test Court', $filteredResponse->json('0.venue'));
    }

    private function createTestVenues(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Test Hall', 'capacity' => 100, 'custodian_id' => $custodian->id]);
        Venue::create(['name' => 'Test Court', 'capacity' => 100, 'custodian_id' => $custodian->id]);
    }
}
