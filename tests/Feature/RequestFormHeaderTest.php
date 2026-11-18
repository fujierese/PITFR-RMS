<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRM-01: the Create Request page shows the form itself; the official header,
 * reference code and TUV NORD mark appear only on the printed form.
 */
class RequestFormHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_request_page_has_no_decorative_header_or_tuv_mark(): void
    {
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);

        $this->actingAs($requester)->get(route('requestor.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('Request for the Use of Facility and Equipment')
            ->assertDontSee('TUVNORD-Logo.png', false)
            ->assertDontSee('PIT-PSMO-F-05-3.7-08');
    }

    public function test_schedule_fields_start_empty_and_whole_day_times_are_cleared_on_switch_back(): void
    {
        // REQ-06.
        \App\Models\Venue::create(['name' => 'Gymnasium', 'custodian_id' => User::factory()->create(['role' => 'custodian-venue'])->id, 'capacity' => 300]);
        $requester = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $html = $this->actingAs($requester)->get(route('requestor.index', ['tab' => 'create']))->assertOk()->getContent();

        foreach (['start_date', 'end_date', 'start_time', 'end_time'] as $field) {
            $this->assertMatchesRegularExpression('/name="venue_schedules\[Gymnasium\]\[' . $field . '\]"[^>]*value=""/', $html, "{$field} should start empty");
        }
        $this->assertStringContainsString("field.dataset.autoFilled = '1';", $html);
        $this->assertStringContainsString("if (field.dataset.autoFilled !== '1') return;", $html);
    }
}
