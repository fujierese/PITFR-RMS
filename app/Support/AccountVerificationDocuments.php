<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Identity and Affiliation Verification: the documents each account type uploads, the
 * backend rules for them, and where they are stored (private disk only).
 *
 *   Student  — PIT Student ID + current Certificate of Registration (COR, academic period checked)
 *   Faculty  — PIT Faculty ID + proof of current PIT affiliation (dated within the last 12 months)
 *   Staff    — PIT Staff ID   + proof of current PIT affiliation (dated within the last 12 months)
 *   Guest    — Government-issued ID only
 *
 * An email address never verifies an account; the Supply Office makes one decision on both.
 */
class AccountVerificationDocuments
{
    public const MEMBER_TYPES = ['student', 'faculty', 'staff'];

    public const MAX_KB = 5120;

    public const MIMES = 'jpg,jpeg,png,pdf';

    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

    /** Faculty and Staff: accepted proof of current PIT service or employment. */
    public const AFFILIATION_PROOFS = [
        'coe' => 'Certificate of Employment (COE)',
        'certificate_of_service' => 'Certificate of Service or Engagement',
        'appointment' => 'Current Appointment Paper',
        'cos' => 'Current Contract of Service (COS)',
        'jo' => 'Current Job Order (JO)',
    ];

    public const AFFILIATION_TYPES = ['cor' => 'Current Certificate of Registration (COR)'] + self::AFFILIATION_PROOFS;

    /** Government-issued IDs accepted for Guests. */
    public const GOVERNMENT_IDS = ['philsys', 'drivers_license', 'passport', 'umid', 'prc', 'postal', 'voters', 'other_government'];

    public static function enabled(): bool
    {
        return app()->bound('pitfr.affiliation_columns')
            ? app('pitfr.affiliation_columns')
            : tap(User::verificationEnabled() && Schema::hasColumn('users', 'verification_affiliation_file'),
                fn (bool $on) => app()->instance('pitfr.affiliation_columns', $on));
    }

    public static function normalizeType(?string $type): string
    {
        return in_array($type, User::CLAIMABLE_REQUESTOR_TYPES, true) ? $type : 'outsider';
    }

    public static function isMember(?string $type): bool
    {
        return in_array($type, self::MEMBER_TYPES, true);
    }

    /** @return array<string, string> ID options for an account type. */
    public static function identityOptions(string $type): array
    {
        $keys = match ($type) {
            'student' => ['pit_student_id'],
            'faculty' => ['pit_faculty_id'],
            'staff' => ['pit_staff_id'],
            default => self::GOVERNMENT_IDS,
        };

        return array_intersect_key(User::VERIFICATION_ID_TYPES, array_flip($keys));
    }

    /** @return array<string, string> second-document options (empty for Guests). */
    public static function affiliationOptions(string $type): array
    {
        return match ($type) {
            'student' => ['cor' => self::AFFILIATION_TYPES['cor']],
            'faculty', 'staff' => self::AFFILIATION_PROOFS,
            default => [],
        };
    }

    /** Academic years a COR may be for: the current one, and next year's from May (enrolment). */
    public static function academicYears(?Carbon $today = null): array
    {
        $today ??= now();
        $start = $today->month >= 8 ? $today->year : $today->year - 1;
        $years = [$start . '-' . ($start + 1)];
        if ($today->month >= 5 && $today->month <= 7) {
            $years[] = ($start + 1) . '-' . ($start + 2);
        }

        return $years;
    }

    /** Backend rules for the documents of the chosen account type. */
    public static function rules(string $type): array
    {
        $file = ['required', 'file', 'mimes:' . self::MIMES, 'max:' . self::MAX_KB];
        $rules = [
            'verification_id_type' => ['required', Rule::in(array_keys(self::identityOptions($type)))],
            'verification_id' => $file,
        ];

        if (! self::enabled()) {
            return $rules;
        }

        if (self::isMember($type)) {
            $rules['verification_affiliation_type'] = ['required', Rule::in(array_keys(self::affiliationOptions($type)))];
            $rules['verification_affiliation'] = $file;
        }
        if ($type === 'student') {
            $rules['cor_academic_year'] = ['required', Rule::in(self::academicYears())];
            $rules['cor_semester'] = ['required', Rule::in(self::SEMESTERS)];
        }
        if (in_array($type, ['faculty', 'staff'], true)) {
            $rules['affiliation_document_date'] = ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . now()->subYear()->toDateString()];
        }

        return $rules;
    }

    public static function messages(): array
    {
        return [
            'verification_id_type.required' => 'Choose the ID you are uploading.',
            'verification_id_type.in' => 'Choose one of the IDs listed for your account type.',
            'verification_id.required' => 'Upload your ID.',
            'verification_id.mimes' => 'The ID must be a JPG, PNG or PDF file.',
            'verification_id.max' => 'The ID must be 5 MB or smaller.',
            'verification_affiliation_type.required' => 'Choose the affiliation document you are uploading.',
            'verification_affiliation_type.in' => 'Choose one of the affiliation documents listed for your account type.',
            'verification_affiliation.required' => 'Upload your Certificate of Registration or proof of current PIT affiliation. A PIT ID alone is not enough.',
            'verification_affiliation.mimes' => 'The affiliation document must be a JPG, PNG or PDF file.',
            'verification_affiliation.max' => 'The affiliation document must be 5 MB or smaller.',
            'cor_academic_year.required' => 'Choose the academic year shown on your COR.',
            'cor_academic_year.in' => 'Your COR must be for the current academic year.',
            'cor_semester.required' => 'Choose the semester shown on your COR.',
            'affiliation_document_date.required' => 'Enter the date your affiliation document was issued.',
            'affiliation_document_date.before_or_equal' => 'The document date cannot be in the future.',
            'affiliation_document_date.after_or_equal' => 'The document must be issued within the last 12 months to show current affiliation.',
        ];
    }

    public static function store(UploadedFile $file): string
    {
        return $file->storeAs('verification-ids', Str::uuid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension()), 'local');
    }

    /**
     * Stores the validated uploads privately and returns the user columns to save.
     *
     * @return array{columns: array<string, mixed>, files: list<string>}
     */
    public static function storeFor(Request $request, string $type): array
    {
        $files = [];
        $columns = [
            'claimed_requestor_type' => $type,
            'verification_status' => 'pending',
            'verification_method' => 'document_review',
            'verification_id_type' => $request->input('verification_id_type'),
            'verification_id_file' => $files[] = self::store($request->file('verification_id')),
            'verification_note' => null,
            'verification_submitted_at' => now(),
        ];

        if (self::enabled()) {
            $columns['verification_affiliation_type'] = self::isMember($type) ? $request->input('verification_affiliation_type') : null;
            $columns['verification_affiliation_file'] = self::isMember($type) ? ($files[] = self::store($request->file('verification_affiliation'))) : null;
            $columns['verification_document_period'] = match (true) {
                $type === 'student' => $request->input('cor_academic_year') . ', ' . $request->input('cor_semester'),
                in_array($type, ['faculty', 'staff'], true) => 'Issued ' . Carbon::parse($request->input('affiliation_document_date'))->format('M j, Y'),
                default => null,
            };
        }

        return ['columns' => $columns, 'files' => $files];
    }

    /** Deletes the stored document files (after a decision or a failed save). */
    public static function deleteFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
