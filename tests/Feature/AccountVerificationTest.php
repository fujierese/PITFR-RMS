<?php

namespace Tests\Feature;

use App\Mail\RegistrationOtp;
use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Decision D4 (REG-01, REG-05): self-registered requestors are verified by a PIT
 * email or a reviewed ID. Outsider rules apply until then; the ID image is deleted
 * after review.
 */
class AccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        config(['pitfr.institutional_email_domains' => 'pit.edu.ph']);
    }

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'privacy_accepted' => '1',
            'office_or_organization' => 'Test Organization', 'organization_type' => 'Private Organization',
            'first_name' => 'Ana',
            'surname' => 'Reyes',
            'username' => 'ana@example.com',
            'password' => 'Password12345!',
            'password_confirmation' => 'Password12345!',
            'requestor_type' => 'outsider',
        ], $overrides);
    }

    public function test_outsider_who_uploads_an_id_waits_for_review(): void
    {
        $this->post(route('register.post'), $this->registration([
            'verification_id_type' => 'drivers_license',
            'verification_id' => $this->idImage('license.jpg'),
        ]))->assertRedirect(route('register.verify'));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertSame('pending', $user->verification_status);
        $this->assertSame('drivers_license', $user->verification_id_type);
        Storage::disk('local')->assertExists($user->verification_id_file);
    }

    public function test_student_with_pit_email_is_verified_by_the_email_code(): void
    {
        $this->post(route('register.post'), $this->registration([
            'username' => 'ana.reyes@pit.edu.ph',
            'requestor_type' => 'student',
        ]))->assertRedirect(route('register.verify'));

        $user = User::where('username', 'ana.reyes@pit.edu.ph')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type); // Outsider rules until the email is confirmed.
        $this->assertNull($user->verification_id_file);

        $otp = null;
        Mail::assertSent(RegistrationOtp::class, function (RegistrationOtp $mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });

        $this->post(route('register.verify.post'), ['otp' => $otp])->assertRedirect(route('requestor.index'));

        $user->refresh();
        $this->assertSame('student', $user->requestor_type);
        $this->assertSame('verified', $user->verification_status);
    }

    public function test_student_without_pit_email_stays_outsider_until_the_pit_id_is_approved(): void
    {
        $this->post(route('register.post'), $this->registration([
            'requestor_type' => 'student',
            'verification_id_type' => 'pit_id',
            'verification_id' => $this->idImage('pit-id.png'),
        ]));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertSame('student', $user->claimed_requestor_type);
        $this->assertSame('pending', $user->verification_status);
    }

    public function test_supply_office_approval_applies_the_category_and_deletes_the_id(): void
    {
        $supply = User::factory()->create(['role' => 'supply_office']);
        $requester = $this->pendingRequester('student');
        $file = $requester->verification_id_file;

        $this->actingAs($supply)->get(route('supply-office.verifications'))
            ->assertOk()->assertSee($requester->name)->assertSee('PIT ID');
        $this->actingAs($supply)->get(route('supply-office.verifications.file', $requester))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['target_user_id' => $requester->id, 'action' => 'verification_id_viewed']);

        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), [
            'decision' => 'approve',
            'requestor_type' => 'student',
        ])->assertSessionHas('success');

        $requester->refresh();
        $this->assertSame('student', $requester->requestor_type);
        $this->assertSame('verified', $requester->verification_status);
        $this->assertSame($supply->id, (int) $requester->verified_by_id);
        $this->assertNull($requester->verification_id_file);
        Storage::disk('local')->assertMissing($file);
    }

    public function test_rejection_needs_a_reason_deletes_the_id_and_allows_a_new_upload(): void
    {
        $supply = User::factory()->create(['role' => 'supply_office']);
        $requester = $this->pendingRequester('outsider');
        $file = $requester->verification_id_file;

        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), ['decision' => 'reject'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), [
            'decision' => 'reject',
            'reason' => 'Photo is blurry',
        ]);
        $requester->refresh();
        $this->assertSame('rejected', $requester->verification_status);
        $this->assertSame('Photo is blurry', $requester->verification_note);
        Storage::disk('local')->assertMissing($file);

        $this->actingAs($requester)->get(route('requestor.settings'))
            ->assertOk()->assertSee('Account verification')->assertSee('Photo is blurry');

        $this->actingAs($requester)->post(route('requestor.settings.verification'), [
            'requestor_type' => 'outsider',
            'verification_id_type' => 'passport',
            'verification_id' => $this->idImage('passport.jpg'),
        ])->assertSessionHas('success');
        $this->assertSame('pending', $requester->fresh()->verification_status);
    }

    public function test_requestors_cannot_open_uploaded_ids(): void
    {
        $requester = $this->pendingRequester('outsider');
        $other = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);

        $this->actingAs($other)->get(route('supply-office.verifications.file', $requester))->assertForbidden();
    }

    public function test_final_approval_waits_for_the_requester_id_review(): void
    {
        $finalApprover = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username')]);
        $requester = $this->pendingRequester('outsider');
        $request = FacilityRequest::factory()->create([
            'requested_by_id' => $requester->id,
            'status' => 'pending',
            'venue_status' => 'approved',
            'equipment_status' => 'approved',
            'supply_office_review_status' => 'confirmed',
            'igp_payment_status' => 'paid',
            'venue' => ['Gymnasium'],
            'equipment' => [],
        ]);

        $this->actingAs($finalApprover)->post(route('request.supply.final-approval', $request))
            ->assertSessionHasErrors();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_existing_accounts_count_as_verified(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->assertSame('verified', $user->fresh()->verification_status);
        $this->assertTrue($user->fresh()->isVerified());
    }

    private function idImage(string $name): UploadedFile
    {
        // Real PNG bytes (no GD extension needed).
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
    }

    private function pendingRequester(string $claimed): User
    {
        $path = $this->idImage('id.jpg')->store('verification-ids', 'local');
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $user->forceFill([
            'claimed_requestor_type' => $claimed,
            'verification_status' => 'pending',
            'verification_method' => 'id_upload',
            'verification_id_type' => $claimed === 'outsider' ? 'philsys' : 'pit_id',
            'verification_id_file' => $path,
            'verification_submitted_at' => now(),
        ])->save();

        return $user->fresh();
    }

    public function test_registering_without_an_id_is_allowed_but_left_unverified(): void
    {
        $this->post(route('register.post'), $this->registration(['requestor_type' => 'faculty']))
            ->assertRedirect(route('register.verify'));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertSame('faculty', $user->claimed_requestor_type);
        $this->assertSame('unverified', $user->verification_status);
        $this->assertFalse($user->isVerified());
    }

    public function test_unknown_category_values_register_as_outsider(): void
    {
        $this->post(route('register.post'), $this->registration(['requestor_type' => 'custodian']));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertSame('outsider', $user->claimed_requestor_type);
    }

    public function test_privacy_notice_must_be_accepted_and_the_time_is_recorded(): void
    {
        // D6 / REG-06.
        $this->get(route('register'))->assertOk()->assertSee('privacy_accepted', false)->assertSee('RA 10173');

        $this->post(route('register.post'), $this->registration(['privacy_accepted' => null]))
            ->assertSessionHasErrors('privacy_accepted');
        $this->assertDatabaseMissing('users', ['username' => 'ana@example.com']);

        $this->post(route('register.post'), $this->registration());
        $this->assertNotNull(User::where('username', 'ana@example.com')->value('privacy_accepted_at'));
    }

    public function test_dashboards_show_the_privacy_card(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->actingAs($user)->get(route('requestor.index'))
            ->assertOk()
            ->assertSee('Help &amp; Information', false)
            ->assertSee('href="' . route('privacy.data-protection') . '"', false);
    }
}
