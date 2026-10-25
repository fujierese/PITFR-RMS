<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\StudentOrganization;
use App\Models\User;
use Database\Seeders\CollegeDepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RequestFormFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_form_shows_outside_organization_guidance_and_summary(): void
    {
        $user = new User([
            'id' => 1,
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'name' => 'Juan Dela Cruz',
            'department' => 'Private Citizen',
        ]);

        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'venueEquipmentVisibilityMap' => ['Aircon' => ['Balay Alumni']],
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('Outsider Guidance', $html);
        $this->assertStringContainsString('Applicable rental fees and payment procedures', $html);
        $this->assertStringContainsString('Selected Items Summary', $html);
        $this->assertStringContainsString('Request Submission Steps', $html);
        $this->assertStringContainsString('Choose Venue & Schedule', $html);
        $this->assertStringContainsString('Calendar availability is not a confirmation.', $html);
        $this->assertStringContainsString('Draft autosave', $html);
        $this->assertStringContainsString('Official Request Form', $html);
        $this->assertStringContainsString('Submission checklist', $html);
        $this->assertStringContainsString('data-venue-availability-url', $html);
        $this->assertStringContainsString("input.addEventListener('change', function () {", $html);
        $this->assertStringContainsString('toggleScheduleFields();', $html);
        $this->assertStringContainsString('field.required = isSelected;', $html);
        $this->assertDoesNotMatchRegularExpression('/name="venue_schedules\\\\[[^"]+\\\\]\\\\[(?:start_time|end_time|reservation_duration)\\\\]"[^>]*disabled/', $html);
        $this->assertStringContainsString('applyVenueDuration(group);', $html);
        $this->assertStringContainsString('window.syncVenueScheduleDuration = (group) => {', $html);
        $this->assertStringContainsString("['08:00', startTime], ['17:00', endTime]", $html);
        $this->assertStringContainsString("radio.name.endsWith('[reservation_duration]')", $html);
        $this->assertStringContainsString('const updateVenueCapacityWarning = () => {', $html);
        $this->assertStringContainsString('participants > capacity', $html);
        $this->assertStringContainsString('capacityWarningBanner.classList.remove(\'hidden\')', $html);
        $this->assertStringContainsString('venueInput.addEventListener(\'change\', updateVenueCapacityWarning)', $html);
        $this->assertStringContainsString('id="venue-calendar-modal"', $html);
        $this->assertStringContainsString('data-open-venue-calendar', $html);
        $this->assertStringContainsString('Choose date on calendar', $html);
        $this->assertStringContainsString('id="venue-calendar-modal-frame"', $html);
        $this->assertStringContainsString('data-close-venue-calendar', $html);
        $this->assertStringContainsString('data-apply-venue-calendar-dates', $html);
        $this->assertStringContainsString('height:min(92vh,900px)', $html);
        $this->assertStringContainsString('event.data.startDate', $html);
        $this->assertStringContainsString('event.data.endDate', $html);
        $this->assertStringContainsString('calendar/embed?date=', $html);
        $this->assertStringContainsString('select=1', $html);
        $this->assertStringNotContainsString('>Calendar</button>', $html);
        $this->assertMatchesRegularExpression('/<div class="venue-schedule mt-4 hidden" data-venue-schedule data-venue-name="Gymnasium">/', $html);
        $this->assertStringContainsString('data-venue-equipment-group data-venue-name="Gymnasium"', $html);
        $this->assertStringContainsString('Equipment by Venue', $html);
        $this->assertStringNotContainsString('Required by venue', $html);
        $this->assertStringNotContainsString('requested-priority', $html);
        $this->assertStringNotContainsString('name="priority"', $html);
        $this->assertStringNotContainsString('Requested Importance', $html);
    }

    public function test_selected_venue_shows_its_schedule_and_included_equipment(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $html = $this->withSession(['_old_input' => ['venue' => ['Balay Alumni']]])
            ->actingAs($requestor)
            ->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match('/name="venue_schedules\[Balay Alumni\]\[start_date\]"\s+required/', $html));
        $this->assertSame(1, preg_match('/name="venue_schedules\[Balay Alumni\]\[start_time\]"\s+required/', $html));
        $this->assertSame(1, preg_match('/name="venue_schedules\[Balay Alumni\]\[end_time\]"\s+required/', $html));
        $this->assertSame(2, preg_match_all('/name="venue_schedules\[Balay Alumni\]\[reservation_duration\]" value="(?:specific_time|whole_day)"(?![^>]*disabled)/', $html));
        foreach (['Sound System', 'Aircon', 'Chairs'] as $equipment) {
            $this->assertSame(1, preg_match('/name="venue_equipment\[Balay Alumni\]\[\]" value="'.preg_quote($equipment, '/').'"[^>]*checked/', $html));
        }
        $this->assertSame(1, preg_match('/data-venue-equipment-group[^>]*data-venue-name="Balay Alumni"/', $html));
    }

    public function test_chic_venue_auto_selects_its_equipment_without_selecting_a_microphone_type(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $html = $this->withSession(['_old_input' => ['venue' => ['Conference Hall & Interaction Center (CHIC)']]])
            ->actingAs($requestor)
            ->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->getContent();

        foreach (['Sound System', 'Iwata Cooler Fans', 'Podium', 'HDMI', 'Television (CHIC)'] as $equipment) {
            $this->assertSame(1, preg_match('/name="venue_equipment\[Conference Hall &amp; Interaction Center \(CHIC\)\]\[\]" value="'.preg_quote($equipment, '/').'"[^>]*checked/', $html));
        }
        $this->assertSame(1, preg_match('/name="venue_equipment\[Conference Hall &amp; Interaction Center \(CHIC\)\]\[\]" value="Wireless Microphones"(?![^>]*checked)/', $html));
        $this->assertSame(1, preg_match('/name="venue_equipment\[Conference Hall &amp; Interaction Center \(CHIC\)\]\[\]" value="Non-Wireless Microphones"(?![^>]*checked)/', $html));
        $this->assertStringContainsString('Choose either Wireless or Non-Wireless Microphones.', $html);
        $this->assertStringContainsString('id="microphone-selection-error"', $html);
        $this->assertStringContainsString('CHIC equipment', $html);
    }

    public function test_request_form_shows_venue_capacity_next_to_venue_name(): void
    {
        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Balay Alumni'],
            'venueCapacityMap' => ['Balay Alumni' => 50],
            'venueDefaultEquipmentMap' => ['Balay Alumni' => []],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('Balay Alumni', $html);
        $this->assertStringContainsString('50 people', $html);
        $this->assertStringContainsString('data-venue-capacities="{&quot;Balay Alumni&quot;:50}"', $html);
        $this->assertStringContainsString('data-venue-default-equipment="{&quot;Balay Alumni&quot;:[]}"', $html);
    }

    public function test_signed_in_requestor_can_check_multiple_venue_availability(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $date = now()->addDays(2)->toDateString();

        $response = $this->actingAs($requestor)->getJson(route('requestor.venue.availability', [
            'venues' => ['Gymnasium', 'Balay Alumni'],
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'reservation_duration' => 'specific_time',
        ]));

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonCount(2, 'venues')
            ->assertJsonPath('venues.0.venue', 'Gymnasium')
            ->assertJsonPath('venues.0.available', true)
            ->assertJsonPath('venues.1.venue', 'Balay Alumni')
            ->assertJsonPath('venues.1.available', true);
    }

    public function test_request_form_marks_initialized_draft_state_to_prevent_duplicate_bindings(): void
    {
        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'student',
        ])->render();

        $this->assertStringContainsString('requestDraftUiInitialized', $html);
    }

    public function test_request_form_shows_default_equipment_options_when_database_is_empty(): void
    {
        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium', 'Balay Alumni'],
            'equipment' => collect(),
            'venueEquipmentVisibilityMap' => ['Aircon' => ['Balay Alumni']],
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('Sound System', $html);
        $this->assertStringContainsString('Wireless Microphones', $html);
        $this->assertStringContainsString('Non-Wireless Microphones', $html);
        $this->assertStringContainsString('Monobloc Chairs', $html);
        $this->assertStringContainsString('type="number" inputmode="numeric" step="1"', $html);
        $this->assertStringContainsString('name="venue_equipment_quantities[Gymnasium][Sound System]"', $html);
        $this->assertStringContainsString('placeholder="Enter qty"', $html);
        $this->assertStringNotContainsString('data-quantity-step', $html);
        $this->assertStringContainsString('Balay Alumni has 50 Chairs onsite.', $html);
        $this->assertStringContainsString('data-name="Aircon" data-venue="Balay Alumni"', $html);
        $this->assertStringNotContainsString('value="Wireless Microphone"', $html);
        $this->assertStringNotContainsString('value="Non-wireless Microphone"', $html);
        $this->assertStringNotContainsString('value="Monobloc chairs"', $html);
        $this->assertStringContainsString('value="Chairs"', $html);
    }

    public function test_request_form_checklist_items_include_scroll_targets_for_missing_fields(): void
    {
        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'student',
        ])->render();

        $this->assertStringContainsString('data-checklist-target="checklist-required-fields"', $html);
        $this->assertStringContainsString('data-checklist-target="checklist-venue-availability"', $html);
        $this->assertStringContainsString('data-checklist-target="checklist-document-upload"', $html);
        $this->assertStringContainsString('data-checklist-target="checklist-e-signature"', $html);
    }

    public function test_external_request_form_uses_consistent_igp_receipt_upload_ui(): void
    {
        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('id="igp_receipt_file"', $html);
        $this->assertStringContainsString('data-upload-file-card', $html);
        $this->assertStringContainsString('data-upload-filename', $html);
        $this->assertStringContainsString('igp-receipt-preview', $html);
    }

    public function test_student_form_displays_trusted_student_organization_as_the_source_of_truth(): void
    {
        $this->seed(CollegeDepartmentSeeder::class);

        $collegeId = \App\Models\College::query()->value('id') ?? 1;
        $departmentId = \App\Models\Department::query()->value('id') ?? 1;

        $student = User::factory()->create([
            'role' => 'requestor',
            'requestor_type' => 'student',
            'name' => 'Student Leader',
            'position' => 'President',
            'department' => 'Computer Science',
            'department_id' => $departmentId,
            'college_id' => $collegeId,
        ]);

        $org = StudentOrganization::create([
            'name' => 'BITS Student Council',
            'is_active' => true,
        ]);

        $student->organizationMemberships()->create([
            'student_organization_id' => $org->id,
            'membership_role' => 'President',
            'can_submit_requests' => true,
            'is_active' => true,
        ]);

        Auth::login($student);

        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'student',
        ])->render();

        $this->assertStringContainsString('Student Organization', $html);
        $this->assertStringContainsString('BITS Student Council', $html);
        $this->assertStringNotContainsString('Department / Requisitioning Office', $html);
        $this->assertStringContainsString('data-upload-status class="hidden text-xs font-medium text-slate-700"', $html);
        $this->assertStringContainsString('data-upload-filename class="truncate text-sm font-semibold text-slate-900"', $html);
    }
}
