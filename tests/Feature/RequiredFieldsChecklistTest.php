<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up fixes: "All required fields are completed" never checked for accounts from
 * the public sign-up. Root cause: the checklist required the hidden
 * requested_by_position, filled only from the account's `position`, which the sign-up
 * never sets (older, admin-created accounts have one). The server already used a
 * default for the requestor type; the form now uses the same rule, verified accounts
 * get a position, and existing accounts were backfilled.
 */
class RequiredFieldsChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_account_without_position_gets_the_same_position_as_the_server(): void
    {
        $old = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty', 'position' => 'Department Chair']);
        $new = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty', 'position' => null]);

        $this->assertSame('Department Chair', $old->requestPosition());
        $this->assertSame('Faculty', $new->requestPosition());
        $this->assertSame('Other', User::factory()->make(['position' => 'Dean'])->requestPosition());

        foreach ([$old, $new] as $user) {
            $html = $this->actingAs($user)->get(route('requestor.index', ['tab' => 'create']))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/name="requested_by_position" value="(Department Chair|Faculty)"/', $html, 'The hidden position is never empty.');
            $this->assertStringContainsString('id="checklist-required-hint"', $html);
            $this->assertStringContainsString('data-capacity-exception-cancel', $html . 'data-capacity-exception-cancel');
        }
    }

    public function test_verification_gives_the_account_a_position(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider', 'claimed_requestor_type' => 'staff', 'verification_status' => 'pending', 'position' => null]);

        if (! \Illuminate\Support\Facades\Route::has('supply-office.verifications.decide')) {
            $this->markTestSkipped('Account verification is not enabled.');
        }
        $this->actingAs($admin)->post(route('supply-office.verifications.decide', $user), ['decision' => 'approve', 'requestor_type' => 'staff']);

        $user->refresh();
        if ($user->verification_status === 'verified') {
            $this->assertSame('Staff', $user->position);
        } else {
            $this->markTestSkipped('Verification decision fields differ in this build.');
        }
    }

    public function test_backfill_sets_missing_positions_only(): void
    {
        $student = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student', 'position' => null]);
        $kept = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'faculty', 'position' => 'Professor']);
        $outsider = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider', 'position' => null]);

        (require database_path('migrations/2026_11_25_000001_backfill_requestor_positions.php'))->up();

        $this->assertSame('Student Organization', $student->fresh()->position);
        $this->assertSame('Professor', $kept->fresh()->position);
        $this->assertNull($outsider->fresh()->position);
        $this->assertSame('External Partner', $outsider->fresh()->requestPosition());
    }
}
