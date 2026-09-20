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

        $nonCollegeOrganizations = [
            ['name' => 'Supreme Student Government (SSG)', 'acronym' => 'SSG', 'category' => 'Mandated College'],
            ['name' => 'Publication Fulcrum', 'acronym' => null, 'category' => 'Mandated College'],
            ['name' => 'Supreme Student Council (SSC)', 'acronym' => 'SSC', 'category' => 'Mandated Highschool'],
            ['name' => 'The Builder', 'acronym' => null, 'category' => 'Mandated Highschool'],
            ['name' => 'Molders of Young Minds (MOYM)', 'acronym' => 'MOYM', 'category' => 'Academic Related'],
            ['name' => 'Society of Healthy and Active Physical Educators (SHAPE)', 'acronym' => 'SHAPE', 'category' => 'Academic Related'],
            ['name' => 'Organization of Aspiring Critical Language Educators (ORACLE)', 'acronym' => 'ORACLE', 'category' => 'Academic Related'],
            ['name' => 'Figure Enthusiast (FE)', 'acronym' => 'FE', 'category' => 'Academic Related'],
            ['name' => 'Kapisanang Filipino (KAFIL)', 'acronym' => 'KAFIL', 'category' => 'Academic Related'],
            ['name' => 'Movement of Social Thinkers (MOST)', 'acronym' => 'MOST', 'category' => 'Academic Related'],
            ['name' => 'Technology Educators Organization (TECHEDO)', 'acronym' => 'TECHEDO', 'category' => 'Academic Related'],
            ['name' => "Graduating Educators' Organization (GEO)", 'acronym' => 'GEO', 'category' => 'Academic Related'],
            ['name' => 'Alliance of Intellectually Molded Scholars (AIMS)', 'acronym' => 'AIMS', 'category' => 'Academic Related'],
            ['name' => 'Kristiyanong Kabataan Para sa Bayan (KKB-PIT)', 'acronym' => 'KKB-PIT', 'category' => 'Religious Activities'],
            ['name' => 'The Enfolders', 'acronym' => null, 'category' => 'Religious Activities'],
            ['name' => 'PIT Campus Ministry', 'acronym' => null, 'category' => 'Religious Activities'],
            ['name' => 'PIT Esports', 'acronym' => null, 'category' => 'Sports'],
            ['name' => 'Absolute Focus', 'acronym' => null, 'category' => 'Sports'],
            ['name' => 'PIT Arnis Association (PITAA)', 'acronym' => 'PITAA', 'category' => 'Sports'],
            ['name' => 'PIT Taekwondo Association (PITTA)', 'acronym' => 'PITTA', 'category' => 'Sports'],
            ['name' => 'Kaapit Sayaw', 'acronym' => null, 'category' => 'Cultural Affairs/Performing Arts'],
            ['name' => 'Mugna', 'acronym' => null, 'category' => 'Cultural Affairs/Performing Arts'],
            ['name' => 'PIT-LHS Drums, Bugle, and Lyre Corps. (PIT-LHS DBLC)', 'acronym' => 'PIT-LHS DBLC', 'category' => 'Cultural Affairs/Performing Arts'],
            ['name' => 'Students Active Volunteers Emergency Responders - Red Cross Youth Council PIT Chapter (SAVERS-RCYC-PIT)', 'acronym' => 'SAVERS-RCYC-PIT', 'category' => 'Emergency Response'],
            ['name' => 'Alpha Phi Omega - Eta Pi Chapter (APO)', 'acronym' => 'APO', 'category' => 'Emergency Response'],
            ['name' => 'Association of Student Assistant Program (ASAP)', 'acronym' => 'ASAP', 'category' => 'Emergency Response'],
        ];

        foreach ($nonCollegeOrganizations as $organization) {
            StudentOrganization::updateOrCreate(
                ['name' => $organization['name']],
                [
                    'acronym' => $organization['acronym'],
                    'college_id' => null,
                    'department_id' => null,
                    'organization_type' => 'Student Organization',
                    'category' => $organization['category'],
                    'adviser' => null,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Student organization master data has been seeded.');
    }
}
