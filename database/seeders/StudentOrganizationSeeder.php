<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Department;
use App\Models\StudentOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class StudentOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('student_organizations') || ! Schema::hasColumn('student_organizations', 'college_id')) {
            $this->command->warn('Student organization relational columns are not present; running seeder skipped until the schema is migrated.');
            return;
        }
        $collegeOrganizations = [
            'College of Technology and Engineering' => [
                'Bonded Information Technology Students (BITS)' => [
                    'acronym' => 'BITS',
                    'category' => 'COTE',
                    'organization_type' => 'Academic',
                    'department_name' => 'Information Technology',
                ],
                'Junior Philippine Institute of Industrial Engineers (JPIIE)' => [
                    'acronym' => 'JPIIE',
                    'category' => 'COTE',
                    'organization_type' => 'Academic',
                    'department_name' => 'Industrial Engineering',
                ],
                'Institute of Integrated Electrical Engineers (IIEE)' => [
                    'acronym' => 'IIEE',
                    'category' => 'COTE',
                    'organization_type' => 'Academic',
                    'department_name' => 'Electrical Engineering',
                ],
                'Industrial Developers of the Land (IDOL)' => [
                    'acronym' => 'IDOL',
                    'category' => 'COTE',
                    'organization_type' => 'Academic',
                    'department_name' => 'Industrial Technology',
                ],
                'Junior Philippine Society of Mechanical Engineers (JPSME)' => [
                    'acronym' => 'JPSME',
                    'category' => 'COTE',
                    'organization_type' => 'Academic',
                    'department_name' => 'Mechanical Engineering',
                ],
            ],
            'College of Arts and Sciences' => [
                'Vital Organization of Intellectual Communicators and Eloquent Speakers (VOICES)' => [
                    'acronym' => 'VOICES',
                    'category' => 'CAS',
                    'organization_type' => 'Academic',
                    'department_name' => 'Communication',
                ],
                'Students Association of Restaurateurs, Hoteliers and International Professional Seafarers (STARSHIPS)' => [
                    'acronym' => 'STARSHIPS',
                    'category' => 'CAS',
                    'organization_type' => 'Academic',
                    'department_name' => 'Hospitality Management',
                ],
                'Marketers Organization' => [
                    'acronym' => 'MO',
                    'category' => 'CAS',
                    'organization_type' => 'Academic',
                    'department_name' => 'Business Administration',
                ],
                'Marine Trident' => [
                    'acronym' => 'MT',
                    'category' => 'CAS',
                    'organization_type' => 'Academic',
                    'department_name' => 'Marine Biology',
                ],
            ],
            'College of Maritime Education' => [
                'Seekers of Adventure Inspired by the Love of the Sea (SAILS)' => [
                    'acronym' => 'SAILS',
                    'category' => 'COMED',
                    'organization_type' => 'Academic',
                    'department_name' => 'Marine Transportation',
                ],
                'Association of Marine Engineering Students of PIT (AMESOP)' => [
                    'acronym' => 'AMESOP',
                    'category' => 'COMED',
                    'organization_type' => 'Academic',
                    'department_name' => 'Marine Engineering',
                ],
            ],
            'College of Teacher Education' => [
                'Future Educators Organization' => [
                    'acronym' => 'FEO',
                    'category' => 'CTE',
                    'organization_type' => 'Academic',
                    'department_name' => 'Elementary Education', 'Technical-Vocational Teacher Education', 'Secondary Education', 'Social Science Education',
                ],
            ],
        ];

        foreach ($collegeOrganizations as $collegeName => $organizations) {
            $college = College::query()->where('name', $collegeName)->first();
            if (! $college) {
                $this->command->warn("Skipping organizations for {$collegeName}: college record not found.");
                continue;
            }

            foreach ($organizations as $name => $meta) {
                $departmentId = null;
                if (! empty($meta['department_name'])) {
                    $departmentId = Department::query()
                        ->where('college_id', $college->id)
                        ->where('name', $meta['department_name'])
                        ->value('id');
                }

                StudentOrganization::updateOrCreate(
                    ['name' => $name],
                    [
                        'acronym' => $meta['acronym'] ?? null,
                        'college_id' => $college->id,
                        'department_id' => $departmentId,
                        'organization_type' => $meta['organization_type'] ?? 'Academic',
                        'category' => $meta['category'] ?? $college->abbreviation,
                        'adviser' => null,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Student organization master data has been seeded. Unconfirmed CTE organizations remain intentionally excluded.');
    }
}
