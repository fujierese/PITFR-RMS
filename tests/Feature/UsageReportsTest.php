<?php

namespace Tests\Feature;

use App\Models\FacilityRequest;
use App\Models\ReservationSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_report_shows_metrics_drilldown_and_monthly_comparison(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor', 'name' => 'Report Requestor']);
        $request = FacilityRequest::create([
            'control_number' => 'FER-REPORT-001',
            'date_requested' => now()->toDateString(),
            'department' => 'Information Technology',
            'name_of_activity' => 'Report Test Activity',
            'expected_participants' => 40,
            'start_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'end_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'requested_by_id' => $requestor->id,
            'requested_by' => $requestor->name,
            'requested_by_position' => 'Student',
            'requesting_date' => now()->toDateString(),
            'time' => '09:00',
            'venue' => ['Conference Hall'],
            'equipment' => ['Sound System'],
            'equipment_quantities' => ['Sound System' => 2],
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);
        $request->requestVenues()->create(['name' => 'Conference Hall']);
        $request->requestEquipment()->create(['name' => 'Sound System', 'quantity' => 2]);
        ReservationSchedule::create([
            'facility_request_id' => $request->id,
            'start_datetime' => now()->startOfMonth()->addDays(4)->setTime(9, 0),
            'end_datetime' => now()->startOfMonth()->addDays(4)->setTime(11, 0),
        ]);

        $previousRequest = FacilityRequest::create([
            'control_number' => 'FER-REPORT-PREVIOUS',
            'date_requested' => now()->toDateString(),
            'department' => 'Information Technology',
            'name_of_activity' => 'Previous Period Activity',
            'expected_participants' => 20,
            'start_date' => now()->startOfMonth()->subDays(10)->toDateString(),
            'end_date' => now()->startOfMonth()->subDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'requested_by_id' => $requestor->id,
            'requested_by' => $requestor->name,
            'requested_by_position' => 'Student',
            'requesting_date' => now()->toDateString(),
            'time' => '09:00',
            'venue' => [],
            'equipment' => [],
            'status' => 'approved',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'priority' => 'regular',
        ]);
        ReservationSchedule::create([
            'facility_request_id' => $previousRequest->id,
            'start_datetime' => now()->startOfMonth()->subDays(10)->setTime(9, 0),
            'end_datetime' => now()->startOfMonth()->subDays(10)->setTime(11, 0),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('supply-office.usage-reports', [
            'preset' => 'custom',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk()
            ->assertSee('Quick date ranges')
            ->assertSee('Previous period')
            ->assertSee('No change')
            ->assertSee('Monthly reservation trend')
            ->assertSee('Reservation details')
            ->assertSee('2 units')
            ->assertSee('1 bookings')
            ->assertSee('40')
            ->assertSee('Report Test Activity')
            ->assertSee('Download CSV')
            ->assertSee('download="usage-report-', false)
            ->assertSee('size: A4 landscape', false)
            ->assertSee('Print report');

        $this->get(route('supply-office.usage-reports', [
            'preset' => 'custom',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
            'breakdown' => 'venue',
            'value' => 'Conference Hall',
        ]))->assertOk()->assertSee('Showing reservations matching')->assertSee('Report Test Activity');

        $csvResponse = $this->get(route('supply-office.usage-reports.export', [
            'preset' => 'custom',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]));
        $csvResponse->assertOk();
        $csv = $csvResponse->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $header = str_getcsv(explode("\n", substr($csv, 3))[0]);
        $this->assertSame([
            'Control Number',
            'Activity',
            'Requestor',
            'Department',
            'Priority',
            'Start',
            'End',
            'Venues',
            'Equipment',
            'Equipment Units',
            'Participants',
        ], $header);
        $this->assertStringContainsString('FER-REPORT-001', $csv);
        $this->assertStringContainsString('Sound System (2)', $csv);
    }

    public function test_usage_report_csv_export_is_admin_only_and_uses_selected_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('supply-office.usage-reports.export', [
            'preset' => 'custom',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'usage-report-' . now()->startOfMonth()->toDateString() . '-to-' . now()->endOfMonth()->toDateString() . '.csv',
            $response->headers->get('content-disposition')
        );

        $requestor = User::factory()->create(['role' => 'requestor']);
        $this->actingAs($requestor)
            ->get(route('supply-office.usage-reports.export'))
            ->assertForbidden();
    }

    public function test_usage_report_rejects_reversed_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('supply-office.usage-reports', [
            'preset' => 'custom',
            'date_from' => now()->endOfMonth()->toDateString(),
            'date_to' => now()->startOfMonth()->toDateString(),
        ]))->assertSessionHasErrors('date_to');
    }

    public function test_usage_report_shows_actionable_empty_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('supply-office.usage-reports', [
            'preset' => 'custom',
            'date_from' => '2020-01-01',
            'date_to' => '2020-01-31',
        ]))->assertOk()
            ->assertSee('No approved reservations in this period.')
            ->assertSee('Try a wider date range')
        ->assertSee('Monthly reservation trend')
        ->assertSee('0', false);
    }
}
