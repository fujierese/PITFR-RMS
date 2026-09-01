<?php
namespace App\Models;

use App\Models\Equipment;
use App\Models\College;
use App\Models\Department;
use App\Models\Venue;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable implements CanResetPassword
{
    use HasFactory, Notifiable, HasApiTokens, CanResetPasswordTrait;

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (empty($user->getAttribute('name')) && (
                ! empty($user->surname)
                || ! empty($user->first_name)
                || ! empty($user->middle_name)
                || ! empty($user->suffix)
            )) {
                $user->attributes['name'] = self::formatFullName(
                    $user->surname,
                    $user->first_name,
                    $user->middle_name,
                    $user->suffix,
                );
            }

            if (! empty($user->getAttribute('name')) && empty($user->surname) && empty($user->first_name) && empty($user->middle_name) && empty($user->suffix)) {
                $parsed = self::parseFullName($user->getAttribute('name'));
                $user->attributes['surname'] = $parsed['surname'];
                $user->attributes['first_name'] = $parsed['first_name'];
                $user->attributes['middle_name'] = $parsed['middle_name'];
                $user->attributes['suffix'] = $parsed['suffix'];
            }
        });
    }

    public static function parseFullName(?string $value): array
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return ['surname' => null, 'first_name' => null, 'middle_name' => null, 'suffix' => null];
        }

        $suffixPattern = '/^(jr\.?|sr\.?|ii|iii|iv|v)$/i';
        $normalized = str_replace(['  ', "\t", "\n", "\r"], ' ', $raw);
        $parts = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($normalized !== '' && str_contains($normalized, ',')) {
            [$surname, $rest] = array_map('trim', explode(',', $normalized, 2));
            $suffix = '';
            $restParts = preg_split('/\s+/', trim((string) $rest), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($restParts !== [] && preg_match($suffixPattern, end($restParts))) {
                $suffix = array_pop($restParts);
            }
            $firstName = $restParts[0] ?? null;
            $middleName = count($restParts) > 1 ? implode(' ', array_slice($restParts, 1)) : null;

            return [
                'surname' => trim((string) $surname) !== '' ? trim((string) $surname) : null,
                'first_name' => $firstName !== '' ? $firstName : null,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'suffix' => $suffix !== '' ? $suffix : null,
            ];
        }

        $suffix = '';
        if ($parts !== [] && preg_match($suffixPattern, end($parts))) {
            $suffix = array_pop($parts);
        }

        if (count($parts) <= 1) {
            return [
                'surname' => count($parts) === 1 ? $parts[0] : null,
                'first_name' => null,
                'middle_name' => null,
                'suffix' => $suffix !== '' ? $suffix : null,
            ];
        }

        $firstName = array_shift($parts);
        $lastName = array_pop($parts);

        return [
            'surname' => $lastName !== '' ? $lastName : null,
            'first_name' => $firstName !== '' ? $firstName : null,
            'middle_name' => $parts !== [] ? implode(' ', $parts) : null,
            'suffix' => $suffix !== '' ? $suffix : null,
        ];
    }

    protected $fillable = [
        'username',
        'e_signature_file',
        'notification_preferences',
        'password',
        'name',
        'surname',
        'first_name',
        'middle_name',
        'suffix',
        'role',
        'is_active',
        'department',
        'college_id',
        'department_id',
        'position',
        'requestor_type',
        'school_id_number',
        'faculty_id',
        'office_or_organization',
        'contact_number',
        'email_verified_at',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'otp_last_sent_at',
        'google_id',
    ];

    public static function formatFullName(?string $surname = null, ?string $firstName = null, ?string $middleName = null, ?string $suffix = null): string
    {
        $surname = trim((string) ($surname ?? ''));
        $firstName = trim((string) ($firstName ?? ''));
        $middleName = trim((string) ($middleName ?? ''));
        $suffix = trim((string) ($suffix ?? ''));

        $given = array_values(array_filter([$firstName, $middleName], static fn ($part) => $part !== ''));

        if ($surname !== '') {
            $combined = implode(' ', $given);
            $formatted = $combined === ''
                ? $surname
                : $surname . ', ' . $combined;

            return $suffix !== '' ? trim($formatted . ' ' . $suffix) : $formatted;
        }

        $combined = implode(' ', $given);
        if ($combined === '') {
            return $suffix !== '' ? $suffix : '';
        }

        return $suffix !== '' ? trim($combined . ' ' . $suffix) : $combined;
    }

    public function getNameAttribute(): string
    {
        return self::formatFullName($this->surname, $this->first_name, $this->middle_name, $this->suffix);
    }

    public function setNameAttribute($value): void
    {
        $raw = trim((string) $value);
        $this->attributes['name'] = $raw !== '' ? $raw : null;

        if ($raw === '') {
            $this->attributes['surname'] = null;
            $this->attributes['first_name'] = null;
            $this->attributes['middle_name'] = null;
            $this->attributes['suffix'] = null;
            return;
        }

        $suffixPattern = '/^(jr\.?|sr\.?|ii|iii|iv|v)$/i';
        $normalized = str_replace(['  ', "\t", "\n", "\r"], ' ', $raw);
        $parts = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($normalized !== '' && str_contains($normalized, ',')) {
            [$surname, $rest] = array_map('trim', explode(',', $normalized, 2));
            $surname = trim((string) $surname);
            $restParts = preg_split('/\s+/', trim((string) $rest), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $suffix = '';
            if ($restParts !== [] && preg_match($suffixPattern, end($restParts))) {
                $suffix = array_pop($restParts);
            }
            $firstName = $restParts[0] ?? null;
            $middleName = count($restParts) > 1 ? implode(' ', array_slice($restParts, 1)) : null;

            $this->attributes['surname'] = $surname !== '' ? $surname : null;
            $this->attributes['first_name'] = $firstName !== '' ? $firstName : null;
            $this->attributes['middle_name'] = $middleName !== '' ? $middleName : null;
            $this->attributes['suffix'] = $suffix !== '' ? $suffix : null;
            return;
        }

        $suffix = '';
        if ($parts !== [] && preg_match($suffixPattern, end($parts))) {
            $suffix = array_pop($parts);
        }

        if (count($parts) <= 1) {
            $this->attributes['surname'] = count($parts) === 1 ? $parts[0] : null;
            $this->attributes['first_name'] = null;
            $this->attributes['middle_name'] = null;
            $this->attributes['suffix'] = $suffix !== '' ? $suffix : null;
            return;
        }

        $firstName = array_shift($parts);
        $lastName = array_pop($parts);

        $this->attributes['surname'] = $lastName !== '' ? $lastName : null;
        $this->attributes['first_name'] = $firstName !== '' ? $firstName : null;
        $this->attributes['middle_name'] = $parts !== [] ? implode(' ', $parts) : null;
        $this->attributes['suffix'] = $suffix !== '' ? $suffix : null;
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'otp_last_sent_at' => 'datetime',
            'notification_preferences' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Override default auth field
    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->username;
    }

    public function routeNotificationForMail(): string
    {
        return $this->username;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isRequestee(): bool
    {
        return in_array($this->role, ['requestor', 'student', 'faculty'], true);
    }

    public function isStudent(): bool
    {
        return $this->requestor_type === 'student';
    }

    public function isFaculty(): bool
    {
        return $this->requestor_type === 'faculty';
    }

    public function isOutsider(): bool
    {
        return $this->requestor_type === 'outsider';
    }

    public function isStudentOrganization(): bool
    {
        return $this->requestor_type === 'student_organization';
    }

    public function getAccountTypeLabelAttribute(): string
    {
        return match ($this->requestor_type) {
            'student' => 'Student',
            'faculty' => 'Faculty',
            'student_organization' => 'Student Organization',
            'outsider' => 'Outsider',
            default => $this->role_label,
        };
    }

    public function isRequestor(): bool
    {
        return $this->role === 'requestor';
    }

    public function isCustodian(): bool
    {
        return $this->role === 'custodian' || str_starts_with($this->role, 'custodian');
    }

    public function isCustodianVenue(): bool
    {
        if ($this->role === 'custodian-venue') {
            return true;
        }

        if ($this->role === 'custodian') {
            return $this->venues()->exists();
        }

        return false;
    }

    public function isCustodianEquipment(): bool
    {
        if ($this->role === 'custodian-equipment') {
            return true;
        }

        if ($this->role === 'custodian') {
            return $this->equipmentItems()->exists();
        }

        return false;
    }

    public function isFacilityAdministrator(): bool
    {
        return in_array($this->role, ['admin', 'facility_admin', 'supply_office'], true);
    }

    public function isSupplyOffice(): bool
    {
        return in_array($this->role, ['admin', 'facility_admin', 'supply_office'], true);
    }

    public function isSystemAdmin(): bool
    {
        return in_array($this->role, ['admin', 'facility_admin', 'supply_office'], true);
    }

    public function isAdminRole(): bool
    {
        return $this->isFacilityAdministrator();
    }

    public function isAdmin(): bool
    {
        return $this->isFacilityAdministrator();
    }

    public function getStudentProgramLabelAttribute(): ?string
    {
        return $this->program ?? $this->course ?? null;
    }

    public function getStudentYearLevelLabelAttribute(): ?string
    {
        return $this->year_level ?? $this->yearLevel ?? null;
    }

    public function getProfileOrganizationLabelAttribute(): string
    {
        return $this->office_or_organization ?: 'Outsider';
    }

    /**
     * Determine custodian type based ONLY on role, not on resource assignment.
     * 
     * custodian-venue → 'venue' (Venue Custodian)
     * custodian-equipment → 'equipment' (Equipment Custodian)
     * custodian → null (generic/legacy; no specific type)
     * 
     * @return string|null 'venue', 'equipment', or null
     */
    public function custodianType(): ?string
    {
        if ($this->role === 'custodian-venue') {
            return 'venue';
        }

        if ($this->role === 'custodian-equipment') {
            return 'equipment';
        }

        // For generic 'custodian' role with no specific suffix, return null
        // Do NOT infer type from resource assignment
        return null;
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'requestor'           => 'Requestor',
            'student'             => 'Student',
            'faculty'             => 'Faculty',
            'outsider'            => 'Outsider',
            'custodian'           => 'Custodian',
            'custodian-venue'     => 'Venue Custodian',
            'custodian-equipment' => 'Equipment Custodian',
            'facility_admin',
            'admin',
            'supply_office'       => 'Supply Office',
            default               => 'User',
        };
    }

    public function facilityRequests()
    {
        return $this->hasMany(FacilityRequest::class, 'requested_by_id');
    }

    public function organizationMemberships()
    {
        return $this->hasMany(StudentOrganizationMember::class);
    }

    public function studentOrganizations()
    {
        return $this->belongsToMany(StudentOrganization::class, 'student_organization_members')
            ->wherePivot('is_active', true)
            ->wherePivot('can_submit_requests', true)
            ->where('student_organizations.is_active', true)
            ->withPivot('is_active', 'membership_role', 'can_submit_requests')
            ->withTimestamps();
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function departmentRecord()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function venues()
    {
        return $this->hasMany(Venue::class, 'custodian_id');
    }

    public function equipmentItems()
    {
        return $this->hasMany(Equipment::class, 'custodian_id');
    }

    public function assignedCustodianResourceLabel(): string
    {
        if ($this->isCustodianVenue()) {
            $names = $this->venues->pluck('name')->filter()->unique();
            return $names->isEmpty() ? 'None' : $names->join(', ');
        }

        if ($this->isCustodianEquipment()) {
            $names = $this->equipmentItems->pluck('name')->filter()->unique();
            return $names->isEmpty() ? 'None' : $names->join(', ');
        }

        return '';
    }

    public function getDashboardRoute()
    {
        if ($this->isAdmin()) {
            return route('supply-office.index');
        }

        if ($this->isCustodian()) {
            return route('custodian.index');
        }

        return route('requestor.index');
    }
}