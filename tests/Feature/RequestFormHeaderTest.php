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
}
