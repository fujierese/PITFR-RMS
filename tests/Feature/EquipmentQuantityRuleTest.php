<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * REQ-12: one equipment quantity rule on the request form, the API and editing:
 * whole numbers from 1 to 1000, every selected item has a quantity, every quantity
 * belongs to a selected item, no duplicates, and only listed equipment.
 */
class EquipmentQuantityRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $requestor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $venueCustodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Gymnasium', 'capacity' => 500, 'custodian_id' => $venueCustodian->id, 'is_active' => true]);
        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        foreach (['Sound System', 'Tables'] as $name) {
            Equipment::factory()->create([
                'name' => $name,
                'custodian_id' => $equipmentCustodian->id,
                'quantity' => 50,
                'quantity_available' => 50,
                'is_active' => true,
            ]);
        }
        $this->requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
    }

    private function submitForm(array $items, array $quantities)
    {
        $date = now()->addDays(5)->toDateString();

        return $this->actingAs($this->requestor)->post(route('requestor.store'), [
            'department' => 'Test Department',
            'request_context' => 'personal',
            'name_of_activity' => 'Quantity rule',
            'expected_participants' => 20,
            'venue' => ['Gymnasium'],
            'venue_schedules' => ['Gymnasium' => [
                'start_date' => $date,
                'end_date' => $date,
                'start_time' => '08:00',
                'end_time' => '10:00',
                'reservation_duration' => 'specific_time',
            ]],
            'venue_equipment' => ['Gymnasium' => $items],
            'venue_equipment_quantities' => ['Gymnasium' => $quantities],
            'activity_proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_the_form_shows_empty_quantity_boxes_with_a_placeholder(): void
    {
        // QA-6: "1" is only a placeholder; the box stays empty until a quantity is entered.
        $html = $this->actingAs($this->requestor)->get(route('requestor.index', ['tab' => 'create']))->assertOk()->getContent();

        preg_match_all('/<input type="number"[^>]*name="venue_equipment_quantities\[[^"]*"[^>]*>/', $html, $boxes);
        $this->assertNotEmpty($boxes[0]);
        foreach ($boxes[0] as $box) {
            $this->assertStringContainsString('value=""', $box);
            $this->assertStringContainsString('placeholder="1"', $box);
        }
    }

    public function test_form_rejects_decimal_zero_text_and_huge_quantities(): void
    {
        foreach (['1.5', '0', '-2', 'abc', '5000'] as $bad) {
            $this->submitForm(['Sound System'], ['Sound System' => $bad])
                ->assertSessionHasErrors('venue_equipment_quantities.Gymnasium.Sound System');
        }
        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_form_rejects_a_missing_quantity_and_a_quantity_without_an_item(): void
    {
        $this->submitForm(['Sound System'], [])->assertSessionHasErrors('equipment');

        $this->submitForm(['Sound System'], ['Sound System' => 1, 'Tables' => 4])
            ->assertSessionHasErrors(['equipment' => 'Gymnasium: A quantity was entered for Tables, which is not selected. Select the item or clear its quantity.']);

        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_form_accepts_whole_number_quantities_for_selected_items(): void
    {
        $this->submitForm(['Sound System', 'Tables'], ['Sound System' => 2, 'Tables' => '10'])
            ->assertSessionHasNoErrors();

        $request = FacilityRequest::query()->firstOrFail();
        $this->assertSame(['Sound System' => 2, 'Tables' => 10], $request->getAdditionalEquipmentQuantities());
    }

    private function apiPost(array $equipment, array $quantities)
    {
        Sanctum::actingAs($this->requestor);

        return $this->postJson('/api/facility-requests', [
            'name_of_activity' => 'API quantity rule',
            'expected_participants' => 10,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'department' => 'IT',
            'venue' => ['Gymnasium'],
            'equipment' => $equipment,
            'equipment_quantities' => $quantities,
        ]);
    }

    public function test_api_rejects_bad_quantities(): void
    {
        foreach ([1.5, 0, -1, 'abc', 5000] as $bad) {
            $this->apiPost(['Tables'], ['Tables' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('equipment_quantities.Tables');
        }
        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_api_requires_a_quantity_per_item_and_an_item_per_quantity(): void
    {
        $this->apiPost(['Tables'], [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipment_quantities' => 'Enter a quantity for Tables.']);

        $this->apiPost(['Tables'], ['Tables' => 2, 'Sound System' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment_quantities');

        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_api_rejects_duplicate_items_and_unlisted_items(): void
    {
        $this->apiPost(['Tables', 'tables'], ['Tables' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment.1');

        $this->apiPost(['Laser Projector'], ['Laser Projector' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment.0');

        $this->assertSame(0, FacilityRequest::count());
    }

    public function test_api_accepts_a_valid_quantity(): void
    {
        $this->apiPost(['Tables'], ['Tables' => 3])->assertSuccessful();

        $this->assertSame(['Tables' => 3], FacilityRequest::query()->firstOrFail()->getEquipmentQuantities());
    }

    private function editableRequest(): FacilityRequest
    {
        $request = FacilityRequest::create([
            'control_number' => 'FER-2026-121',
            'date_requested' => now()->toDateString(),
            'department' => 'IT',
            'name_of_activity' => 'Editable',
            'expected_participants' => 20,
            'start_date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => ['Gymnasium'],
            'equipment' => ['Tables'],
            'equipment_quantities' => ['Tables' => 2],
            'requested_by_id' => $this->requestor->id,
            'status' => 'pending',
            'venue_status' => 'pending',
            'equipment_status' => 'pending',
        ]);
        $request->syncRelationalItems();

        return $request;
    }

    private function edit(FacilityRequest $request, array $equipment, array $quantities)
    {
        return $this->actingAs($this->requestor)->put(route('requestor.update', $request), [
            'name_of_activity' => 'Editable',
            'department' => 'IT',
            'start_date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'venue' => 'Gymnasium',
            'equipment' => $equipment,
            'equipment_quantities' => $quantities,
        ]);
    }

    public function test_editing_rejects_decimal_quantities_duplicates_and_unlisted_items(): void
    {
        $request = $this->editableRequest();

        // QA-7: editing applies the request form's rules.
        $this->edit($request, ['Tables'], ['Tables' => '2.5'])->assertSessionHasErrors('equipment_quantities.Tables');
        $this->edit($request, ['Laser Projector'], ['Laser Projector' => 1])->assertSessionHasErrors('equipment');

        $this->assertSame(['Tables' => 2], $request->fresh()->getEquipmentQuantities());
    }

    public function test_editing_follows_the_form_rule_for_quantities_of_unticked_items(): void
    {
        $request = $this->editableRequest();

        // QA-7: the edit page is the request form, which clears the box of an unticked
        // item; a quantity sent for an item that is not selected is refused, as on the form.
        $this->edit($request, ['Sound System'], ['Tables' => 2, 'Sound System' => 1])->assertSessionHasErrors('equipment');

        $this->edit($request, ['Sound System'], ['Sound System' => 1])->assertSessionHasNoErrors();
        $quantities = $request->fresh()->getEquipmentQuantities();
        $this->assertArrayNotHasKey('Tables', $quantities);
        $this->assertSame(1, $quantities['Sound System'] ?? null);
    }
}
