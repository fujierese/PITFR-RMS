<?php

namespace Tests\Feature;

use App\Http\Middleware\CleanTextInput;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use App\Support\CsvCell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REQ-15: text is trimmed, blank-looking entries count as empty, control characters
 * are removed, lengths and numbers have limits, and user text is shown and exported
 * as plain text.
 */
class TextInputHygieneTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $custodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function submit(array $overrides = [])
    {
        $date = now()->addDays(5)->toDateString();

        return $this->actingAs($this->requestor)->post(route('requestor.store'), $overrides + [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Text hygiene',
            'purpose' => 'Checking text input',
            'expected_participants' => 20,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => [
                'start_date' => $date, 'end_date' => $date,
                'start_time' => '08:00', 'end_time' => '10:00',
                'reservation_duration' => 'specific_time',
            ]],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_blank_looking_activity_names_are_rejected(): void
    {
        foreach (['     ', "\u{00A0}\u{00A0}\u{00A0}", "\u{3000}", "\u{200B}\t\u{200B}"] as $blank) {
            $this->submit(['name_of_activity' => $blank])->assertSessionHasErrors('name_of_activity');
        }

        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_text_is_trimmed_and_control_characters_removed_but_line_breaks_kept(): void
    {
        $this->submit([
            'name_of_activity' => "  \u{00A0}Seminar\x00 on\x07 Safety  ",
            'purpose' => "Line one\nLine two\x1B",
        ])->assertSessionHasNoErrors();

        $request = FacilityRequest::query()->sole();
        $this->assertSame('Seminar on Safety', $request->name_of_activity);
        $this->assertSame("Line one\nLine two", $request->purpose);
    }

    public function test_overlong_text_and_huge_numbers_are_rejected(): void
    {
        $this->submit(['name_of_activity' => str_repeat('a', 201)])->assertSessionHasErrors('name_of_activity');
        $this->submit(['purpose' => str_repeat('a', 2001)])->assertSessionHasErrors('purpose');
        $this->submit(['expected_participants' => 100001])->assertSessionHasErrors('expected_participants');

        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_passwords_are_left_exactly_as_typed(): void
    {
        $request = Request::create('/x', 'POST', ['password' => "  pa\x07ss  ", 'name' => "  Ana\x07  "]);
        (new CleanTextInput())->handle($request, fn () => null);

        $this->assertSame("  pa\x07ss  ", $request->input('password'));
        $this->assertSame('Ana', $request->input('name'));
    }

    public function test_markup_in_request_text_is_shown_as_plain_text(): void
    {
        $this->submit(['name_of_activity' => '<script>alert(1)</script> Night'])->assertSessionHasNoErrors();
        $request = FacilityRequest::query()->sole();

        $this->actingAs($this->requestor)->get(route('request.show', $request))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Night', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_csv_cells_that_look_like_formulas_are_exported_as_text(): void
    {
        $this->assertSame(["'=HYPERLINK(\"x\")", "' =1+1", "'@SUM(A1)", 'Plain', -5, '-5', null], CsvCell::row(['=HYPERLINK("x")', ' =1+1', '@SUM(A1)', 'Plain', -5, '-5', null]));

        $this->submit(['name_of_activity' => '=cmd|calc'])->assertSessionHasNoErrors();
        $supply = User::factory()->create(['role' => 'supply_office']);

        $csv = $this->actingAs($supply)->get(route('supply-office.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=cmd|calc", $csv);
    }
}
