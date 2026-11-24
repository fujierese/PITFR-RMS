<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\Holiday;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * QA-7 / QA-8: editing uses the request form itself: every field, pre-filled, with the
 * same rules and checks; documents are kept unless replaced; the request keeps its
 * control number and goes back to review.
 */
class EditRequestFormTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        Holiday::query()->delete();
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);
        Equipment::create(['name' => 'Tables', 'quantity' => 20, 'quantity_available' => 20, 'custodian_id' => $equipmentCustodian->id, 'is_active' => true]);
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $this->date = now()->addDays(8)->toDateString();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'department' => 'IT',
            'request_context' => 'personal',
            'name_of_activity' => 'Club Fair',
            'purpose' => 'Showcase student clubs',
            'expected_participants' => 80,
            'venue' => ['Covered Court'],
            'venue_schedules' => ['Covered Court' => ['start_date' => $this->date, 'end_date' => $this->date, 'start_time' => '09:00', 'end_time' => '12:00', 'reservation_duration' => 'specific_time']],
            'venue_equipment' => ['Covered Court' => ['Tables']],
            'venue_equipment_quantities' => ['Covered Court' => ['Tables' => 6]],
        ], $overrides);
    }

    private function submitted(): FacilityRequest
    {
        $this->actingAs($this->requestor)->post(route('requestor.store'), $this->payload([
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'proposal_submission_type' => 'final',
        ]))->assertSessionHasNoErrors();

        return FacilityRequest::query()->sole();
    }

    private function save(FacilityRequest $request, array $overrides = [])
    {
        return $this->actingAs($this->requestor)->put(route('requestor.update', $request), $this->payload($overrides + [
            'form_version' => \App\Http\Controllers\RequestorController::editFormVersion($request->fresh()),
        ]));
    }

    public function test_the_edit_page_is_the_full_form_filled_in_from_the_request(): void
    {
        $request = $this->submitted();

        $html = $this->actingAs($this->requestor)->get(route('requestor.edit', $request))->assertOk()
            ->assertSee('Edit Request')
            ->assertSee('Save Changes')
            ->assertSee('Showcase student clubs')
            ->assertSee('Current file: proposal.pdf. It is kept unless you upload a replacement.')
            ->assertSee('The control number, date requested and your account details')
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="form_version"', false)
            ->assertDontSee('name="submission_token"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/name="venue\[\]" value="Covered Court"\s+checked/', $html);
        $this->assertStringContainsString('name="expected_participants" required min="1" max="100000" value="80"', $html);
        $this->assertStringContainsString('value="' . $this->date . '"', $html);
        $this->assertMatchesRegularExpression('/name="venue_equipment_quantities\[Covered Court\]\[Tables\]"[^>]*value="6"/', $html);
        $this->assertMatchesRegularExpression('/id="activity_proposal_file"[^>]*data-kept="1"/', $html);
    }

    public function test_saving_keeps_the_request_identity_and_existing_documents(): void
    {
        $request = $this->submitted();
        $original = $request->only(['control_number', 'date_requested', 'requested_by_id', 'activity_proposal_file']);

        $this->save($request, ['name_of_activity' => 'Club Fair 2026', 'expected_participants' => 120])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('request.show', $request))
            ->assertSessionHas('success', 'Your changes were saved. The request is back in review.');

        $fresh = $request->fresh();
        $this->assertSame('Club Fair 2026', $fresh->name_of_activity);
        $this->assertSame(120, (int) $fresh->expected_participants);
        $this->assertSame($original['control_number'], $fresh->control_number);
        $this->assertSame($original['activity_proposal_file'], $fresh->activity_proposal_file, 'The proposal is kept.');
        $this->assertTrue(Storage::disk('local')->exists('documents/activity_proposal/' . $fresh->activity_proposal_file));
        $this->assertSame('pending', $fresh->status);
        $this->assertTrue($fresh->histories()->where('action', 'requestor_edited')->exists());
    }

    public function test_uploading_a_new_document_replaces_the_old_one(): void
    {
        $request = $this->submitted();
        $oldFile = $request->activity_proposal_file;

        $this->save($request, ['activity_proposal_file' => UploadedFile::fake()->create('proposal-v2.pdf', 100, 'application/pdf'), 'proposal_submission_type' => 'final'])
            ->assertSessionHasNoErrors();

        $fresh = $request->fresh();
        $this->assertNotSame($oldFile, $fresh->activity_proposal_file);
        $this->assertSame('proposal-v2.pdf', $fresh->document_metadata['activity_proposal']['original_name']);
        // Additional Part 2: earlier proposal versions are kept, never deleted.
        $this->assertTrue(Storage::disk('local')->exists('documents/activity_proposal/' . $oldFile));
        $this->assertTrue(Storage::disk('local')->exists('documents/activity_proposal/' . $fresh->activity_proposal_file));
    }

    public function test_editing_applies_the_request_form_rules(): void
    {
        $request = $this->submitted();

        $this->save($request, ['name_of_activity' => '   '])->assertSessionHasErrors('name_of_activity');
        $this->save($request, ['expected_participants' => 0])->assertSessionHasErrors('expected_participants');
        $this->save($request, ['venue_schedules' => ['Covered Court' => ['start_time' => '03:00']]])
            ->assertSessionHasErrors('venue_schedules.Covered Court.start_time');
        $this->save($request, ['venue_equipment_quantities' => ['Covered Court' => ['Tables' => 2.5]]])
            ->assertSessionHasErrors();

        $this->assertSame('Club Fair', $request->fresh()->name_of_activity);
    }

    public function test_an_approved_request_cannot_be_edited(): void
    {
        $request = $this->submitted();
        $request->update(['status' => 'approved', 'venue_status' => 'approved', 'equipment_status' => 'approved']);

        $this->actingAs($this->requestor)->get(route('requestor.edit', $request))->assertForbidden();
        $this->save($request)->assertForbidden();
    }
}
