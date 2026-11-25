<?php

namespace Tests\Feature;

use App\Mail\RegistrationOtp;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Support\AccountVerificationDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Identity and Affiliation Verification (combined review).
 *   Student — PIT Student ID + current COR;  Faculty / Staff — PIT ID + proof of current
 *   affiliation;  Guest — government-issued ID.  JPG / PNG / PDF up to 5 MB.
 * No automatic verification by email domain. The Supply Office approves, requests
 * additional documents, or rejects (reason required); the files are deleted after review.
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
            'verification_id_type' => 'philsys',
            'verification_id' => $this->idImage('national-id.png'),
        ], $overrides);
    }

    private function studentDocuments(array $overrides = []): array
    {
        return array_merge([
            'requestor_type' => 'student',
            'verification_id_type' => 'pit_student_id',
            'verification_id' => $this->idImage('pit-id.png'),
            'verification_affiliation_type' => 'cor',
            'verification_affiliation' => UploadedFile::fake()->create('cor.pdf', 300, 'application/pdf'),
            'cor_academic_year' => AccountVerificationDocuments::academicYears()[0],
            'cor_semester' => '1st Semester',
        ], $overrides);
    }

    private function facultyDocuments(array $overrides = []): array
    {
        return array_merge([
            'requestor_type' => 'faculty',
            'verification_id_type' => 'pit_faculty_id',
            'verification_id' => $this->idImage('faculty-id.png'),
            'verification_affiliation_type' => 'coe',
            'verification_affiliation' => UploadedFile::fake()->create('coe.pdf', 300, 'application/pdf'),
            'affiliation_document_date' => now()->subMonths(2)->toDateString(),
        ], $overrides);
    }

    public function test_registration_page_shows_the_renamed_section_without_email_verification(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('Identity and Affiliation Verification')
            ->assertSee('Current Certificate of Registration (COR)')
            ->assertSee('Certificate of Employment (COE)')
            ->assertSee('Current Job Order (JO)')
            ->assertSee('PIT Student ID')
            ->assertDontSee('verifies your account automatically')
            ->assertDontSee('@pit.edu.ph');
    }

    public function test_guest_needs_a_government_id(): void
    {
        $this->post(route('register.post'), $this->registration(['verification_id' => null]))
            ->assertSessionHasErrors('verification_id');
        $this->post(route('register.post'), $this->registration(['verification_id_type' => 'pit_student_id']))
            ->assertSessionHasErrors('verification_id_type');
        $this->assertDatabaseMissing('users', ['username' => 'ana@example.com']);

        $this->post(route('register.post'), $this->registration())->assertRedirect(route('register.verify'));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertSame('pending', $user->verification_status);
        $this->assertSame('philsys', $user->verification_id_type);
        $this->assertNull($user->verification_affiliation_file);
        Storage::disk('local')->assertExists($user->verification_id_file);
    }

    public function test_student_needs_the_pit_id_and_a_current_cor(): void
    {
        // A PIT ID alone is not enough.
        $this->post(route('register.post'), $this->registration($this->studentDocuments([
            'verification_affiliation' => null, 'verification_affiliation_type' => null,
        ])))->assertSessionHasErrors(['verification_affiliation', 'verification_affiliation_type']);
        // A COR from an old academic year is refused.
        $this->post(route('register.post'), $this->registration($this->studentDocuments(['cor_academic_year' => '2019-2020'])))
            ->assertSessionHasErrors('cor_academic_year');
        // A guest ID is not a PIT Student ID.
        $this->post(route('register.post'), $this->registration($this->studentDocuments(['verification_id_type' => 'philsys'])))
            ->assertSessionHasErrors('verification_id_type');
        $this->assertDatabaseMissing('users', ['username' => 'ana@example.com']);

        $this->post(route('register.post'), $this->registration($this->studentDocuments()))->assertRedirect(route('register.verify'));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type, 'Guest rules apply until the Supply Office approves.');
        $this->assertSame('student', $user->claimed_requestor_type);
        $this->assertSame('pending', $user->verification_status);
        $this->assertSame('cor', $user->verification_affiliation_type);
        $this->assertSame(AccountVerificationDocuments::academicYears()[0] . ', 1st Semester', $user->verification_document_period);
        Storage::disk('local')->assertExists($user->verification_id_file);
        Storage::disk('local')->assertExists($user->verification_affiliation_file);
    }

    public function test_faculty_and_staff_need_current_proof_of_affiliation(): void
    {
        $this->post(route('register.post'), $this->registration($this->facultyDocuments(['affiliation_document_date' => now()->subYears(2)->toDateString()])))
            ->assertSessionHasErrors('affiliation_document_date');
        $this->post(route('register.post'), $this->registration($this->facultyDocuments(['verification_affiliation_type' => 'cor'])))
            ->assertSessionHasErrors('verification_affiliation_type');
        $this->post(route('register.post'), $this->registration($this->facultyDocuments([
            'requestor_type' => 'staff', 'verification_id_type' => 'pit_staff_id', 'verification_affiliation_type' => 'jo',
        ])))->assertRedirect(route('register.verify'));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('staff', $user->claimed_requestor_type);
        $this->assertSame('jo', $user->verification_affiliation_type);
        $this->assertStringStartsWith('Issued ', $user->verification_document_period);
    }

    public function test_invalid_or_oversized_documents_are_refused(): void
    {
        $this->post(route('register.post'), $this->registration(['verification_id' => UploadedFile::fake()->create('id.txt', 10, 'text/plain')]))
            ->assertSessionHasErrors('verification_id');
        $this->post(route('register.post'), $this->registration(['verification_id' => UploadedFile::fake()->create('id.pdf', 6000, 'application/pdf')]))
            ->assertSessionHasErrors('verification_id');
        $this->assertDatabaseMissing('users', ['username' => 'ana@example.com']);
    }

    public function test_a_pit_email_does_not_verify_the_account(): void
    {
        $this->post(route('register.post'), $this->registration($this->studentDocuments(['username' => 'ana.reyes@pit.edu.ph'])))
            ->assertRedirect(route('register.verify'));

        $otp = null;
        Mail::assertSent(RegistrationOtp::class, function (RegistrationOtp $mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });
        $this->post(route('register.verify.post'), ['otp' => $otp])->assertRedirect(route('requestor.index'));

        $user = User::where('username', 'ana.reyes@pit.edu.ph')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertSame('pending', $user->verification_status);
        $this->assertFalse($user->isVerified());
    }

    public function test_one_approval_covers_identity_and_affiliation_and_deletes_both_files(): void
    {
        $supply = User::factory()->create(['role' => 'supply_office']);
        $requester = $this->pendingRequester('student');
        $files = [$requester->verification_id_file, $requester->verification_affiliation_file];

        $this->actingAs($supply)->get(route('supply-office.verifications'))
            ->assertOk()->assertSee($requester->name)->assertSee('PIT Student ID')
            ->assertSee('Current Certificate of Registration (COR)')
            ->assertSee('Approve Account')->assertSee('Request Additional Documents')->assertSee('Reject Application');
        $this->actingAs($supply)->get(route('supply-office.verifications.file', $requester))->assertOk();
        $this->actingAs($supply)->get(route('supply-office.verifications.affiliation-file', $requester))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['target_user_id' => $requester->id, 'action' => 'verification_affiliation_viewed']);

        // The reviewer must confirm both documents were checked.
        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), ['decision' => 'approve', 'requestor_type' => 'student'])
            ->assertSessionHasErrors('documents_checked');
        $this->assertSame('pending', $requester->fresh()->verification_status);

        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), [
            'decision' => 'approve', 'requestor_type' => 'student', 'documents_checked' => '1',
        ])->assertSessionHas('success');

        $requester->refresh();
        $this->assertSame('student', $requester->requestor_type);
        $this->assertSame('verified', $requester->verification_status);
        $this->assertSame($supply->id, (int) $requester->verified_by_id);
        $this->assertNull($requester->verification_id_file);
        $this->assertNull($requester->verification_affiliation_file);
        $this->assertSame('cor', $requester->verification_affiliation_type, 'The document type is kept.');
        Storage::disk('local')->assertMissing($files[0]);
        Storage::disk('local')->assertMissing($files[1]);
        $this->assertDatabaseHas('audit_logs', ['target_user_id' => $requester->id, 'action' => 'user_verified', 'actor_id' => $supply->id]);
        $this->actingAs($supply)->get(route('supply-office.verifications'))->assertSee('Decision history')->assertSee('Approved');
    }

    public function test_a_pit_id_alone_cannot_be_approved_for_a_member_account(): void
    {
        $supply = User::factory()->create(['role' => 'supply_office']);
        $requester = $this->pendingRequester('faculty', withAffiliation: false);

        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), [
            'decision' => 'approve', 'requestor_type' => 'faculty', 'documents_checked' => '1',
        ])->assertSessionHasErrors('decision');
        $this->assertSame('pending', $requester->fresh()->verification_status);
        $this->assertSame('outsider', $requester->fresh()->requestor_type);

        // A COR does not prove Faculty affiliation either.
        $student = $this->pendingRequester('student');
        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $student), [
            'decision' => 'approve', 'requestor_type' => 'faculty', 'documents_checked' => '1',
        ])->assertSessionHasErrors('decision');
        $this->assertSame('pending', $student->fresh()->verification_status);
    }

    public function test_additional_documents_keep_the_account_pending_until_uploaded_again(): void
    {
        $supply = User::factory()->create(['role' => 'supply_office']);
        $requester = $this->pendingRequester('student');
        $files = [$requester->verification_id_file, $requester->verification_affiliation_file];

        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), ['decision' => 'request_documents'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), [
            'decision' => 'request_documents', 'reason' => 'Upload the COR for the current semester.',
        ])->assertSessionHas('success');

        $requester->refresh();
        $this->assertSame('documents_requested', $requester->verification_status);
        $this->assertSame('outsider', $requester->requestor_type);
        $this->assertFalse($requester->isVerified());
        Storage::disk('local')->assertMissing($files[0]);
        Storage::disk('local')->assertMissing($files[1]);

        $this->actingAs($requester)->get(route('requestor.settings'))
            ->assertOk()->assertSee('Identity and Affiliation Verification')
            ->assertSee('Upload the COR for the current semester.')->assertSee('Send for review');

        // The documents for the account type are required again.
        $this->actingAs($requester)->post(route('requestor.settings.verification'), ['requestor_type' => 'student', 'verification_id_type' => 'pit_student_id', 'verification_id' => $this->idImage('id.png')])
            ->assertSessionHasErrors('verification_affiliation');
        $this->actingAs($requester)->post(route('requestor.settings.verification'), $this->studentDocuments())->assertSessionHas('success');
        $this->assertSame('pending', $requester->fresh()->verification_status);

        // While pending, nothing else can be sent.
        $this->actingAs($requester)->post(route('requestor.settings.verification'), $this->studentDocuments())->assertSessionHas('info');
    }

    public function test_rejection_needs_a_reason_and_allows_a_new_application(): void
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
        $this->assertDatabaseHas('audit_logs', ['target_user_id' => $requester->id, 'action' => 'user_verification_rejected']);

        $this->actingAs($requester)->get(route('requestor.settings'))
            ->assertOk()->assertSee('Photo is blurry');

        $this->actingAs($requester)->post(route('requestor.settings.verification'), [
            'requestor_type' => 'outsider',
            'verification_id_type' => 'passport',
            'verification_id' => $this->idImage('passport.jpg'),
        ])->assertSessionHas('success');
        $this->assertSame('pending', $requester->fresh()->verification_status);
    }

    public function test_a_decided_account_cannot_be_decided_again(): void
    {
        $supply = User::factory()->create(['role' => 'supply_office']);
        $requester = $this->pendingRequester('outsider');
        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), ['decision' => 'reject', 'reason' => 'Wrong name']);

        $this->actingAs($supply)->post(route('supply-office.verifications.decide', $requester), [
            'decision' => 'approve', 'requestor_type' => 'outsider', 'documents_checked' => '1',
        ])->assertSessionHas('info');
        $this->assertSame('rejected', $requester->fresh()->verification_status);
    }

    public function test_requestors_cannot_open_documents_or_decide(): void
    {
        $requester = $this->pendingRequester('student');
        $other = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);

        $this->actingAs($other)->get(route('supply-office.verifications.file', $requester))->assertForbidden();
        $this->actingAs($other)->get(route('supply-office.verifications.affiliation-file', $requester))->assertForbidden();
        $this->actingAs($requester)->post(route('supply-office.verifications.decide', $requester), [
            'decision' => 'approve', 'requestor_type' => 'student', 'documents_checked' => '1',
        ])->assertForbidden();
        $this->assertSame('pending', $requester->fresh()->verification_status);
    }

    public function test_user_management_cannot_skip_the_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requester = $this->pendingRequester('student');

        $this->actingAs($admin)->put(route('admin.users.update', $requester), [
            'username' => $requester->username,
            'role' => 'requestor',
            'requestor_type' => 'student',
        ])->assertSessionHas('error');
        $this->assertSame('outsider', $requester->fresh()->requestor_type);
        $this->assertSame('pending', $requester->fresh()->verification_status);
    }

    public function test_final_approval_waits_for_the_requester_review(): void
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

    public function test_unknown_category_values_register_as_outsider(): void
    {
        $this->post(route('register.post'), $this->registration(['requestor_type' => 'custodian']));

        $user = User::where('username', 'ana@example.com')->firstOrFail();
        $this->assertSame('outsider', $user->requestor_type);
        $this->assertSame('outsider', $user->claimed_requestor_type);
    }

    public function test_sign_up_has_no_privacy_consent_while_the_policy_is_hidden(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertDontSee('name="privacy_accepted"', false)
            ->assertDontSee('RA 10173');

        $this->post(route('register.post'), $this->registration(['privacy_accepted' => null]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'ana@example.com']);
    }

    public function test_dashboards_do_not_show_the_privacy_card(): void
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);

        $this->actingAs($user)->get(route('requestor.index'))
            ->assertOk()
            ->assertDontSee('Data Privacy Act of 2012')
            ->assertDontSee('/privacy-and-data-protection', false);
    }

    private function idImage(string $name): UploadedFile
    {
        // Real PNG bytes (no GD extension needed).
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
    }

    private function pendingRequester(string $claimed, bool $withAffiliation = true): User
    {
        $user = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'outsider']);
        $member = AccountVerificationDocuments::isMember($claimed);
        $user->forceFill([
            'claimed_requestor_type' => $claimed,
            'verification_status' => 'pending',
            'verification_method' => 'document_review',
            'verification_id_type' => match ($claimed) {
                'student' => 'pit_student_id', 'faculty' => 'pit_faculty_id', 'staff' => 'pit_staff_id', default => 'philsys',
            },
            'verification_id_file' => $this->idImage('id.png')->store('verification-ids', 'local'),
            'verification_affiliation_type' => $member && $withAffiliation ? ($claimed === 'student' ? 'cor' : 'coe') : null,
            'verification_affiliation_file' => $member && $withAffiliation ? $this->idImage('doc.png')->store('verification-ids', 'local') : null,
            'verification_submitted_at' => now(),
        ])->save();

        return $user->fresh();
    }
}
