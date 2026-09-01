<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentOrganizationMember extends Model
{
    protected $fillable = [
        'user_id', 'student_organization_id', 'membership_role', 'can_submit_requests', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'can_submit_requests' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(StudentOrganization::class, 'student_organization_id');
    }
}