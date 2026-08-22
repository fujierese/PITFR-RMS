<?php
namespace App\Models;

use App\Models\Equipment;
use App\Models\College;
use App\Models\Department;
use App\Models\Venue;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'username',
        'password',
        'name',
        'role',
        'department',
        'college_id',
        'department_id',
        'requestor_type',
        'school_id_number',
        'office_or_organization',
        'contact_number',
    ];

    protected $hidden = ['password', 'remember_token'];

    // Override default auth field
    public function getAuthIdentifierName(): string
    {
        return 'username';
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
        return $this->office_or_organization ?: 'External Requestor';
    }

    public function custodianType(): ?string
    {
        if ($this->role === 'custodian-venue') {
            return 'venue';
        }

        if ($this->role === 'custodian-equipment') {
            return 'equipment';
        }

        if ($this->role === 'custodian') {
            if ($this->venues()->exists()) {
                return 'venue';
            }
            if ($this->equipmentItems()->exists()) {
                return 'equipment';
            }
        }

        return null;
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            'requestor'           => 'Requestor',
            'student'             => 'Student',
            'faculty'             => 'Faculty',
            'outsider'            => 'External Requestor',
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