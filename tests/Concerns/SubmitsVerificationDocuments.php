<?php

namespace Tests\Concerns;

use App\Support\AccountVerificationDocuments;
use Illuminate\Http\UploadedFile;

/**
 * Identity and Affiliation Verification: sign-up now requires the documents for the
 * account type. Tests about other sign-up rules post without them; this adds valid
 * documents for the submitted type unless the test sends its own.
 * (Document rules themselves are covered by AccountVerificationTest.)
 */
trait SubmitsVerificationDocuments
{
    public function post($uri, array $data = [], array $headers = [])
    {
        if ($uri === route('register.post') && ! array_key_exists('verification_id', $data)) {
            $data += self::verificationDocumentsFor(AccountVerificationDocuments::normalizeType($data['requestor_type'] ?? null));
        }

        return parent::post($uri, $data, $headers);
    }

    protected static function verificationDocumentsFor(string $type): array
    {
        $pdf = fn (string $name) => UploadedFile::fake()->create($name, 120, 'application/pdf');

        return match ($type) {
            'student' => [
                'verification_id_type' => 'pit_student_id', 'verification_id' => $pdf('id.pdf'),
                'verification_affiliation_type' => 'cor', 'verification_affiliation' => $pdf('cor.pdf'),
                'cor_academic_year' => AccountVerificationDocuments::academicYears()[0], 'cor_semester' => '1st Semester',
            ],
            'faculty', 'staff' => [
                'verification_id_type' => 'pit_' . $type . '_id', 'verification_id' => $pdf('id.pdf'),
                'verification_affiliation_type' => 'coe', 'verification_affiliation' => $pdf('coe.pdf'),
                'affiliation_document_date' => now()->subMonth()->toDateString(),
            ],
            default => ['verification_id_type' => 'philsys', 'verification_id' => $pdf('id.pdf')],
        };
    }
}
