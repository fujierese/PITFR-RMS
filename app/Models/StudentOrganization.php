<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentOrganization extends Model
{
    protected $fillable = [
        'name', 'acronym', 'college_id', 'department_id', 'organization_type',
        'category', 'is_active', 'adviser',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function memberships()
    {
        return $this->hasMany(StudentOrganizationMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'student_organization_members')
            ->withPivot('is_active')
            ->withTimestamps();
    }
}