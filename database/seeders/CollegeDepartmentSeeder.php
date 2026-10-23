<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CollegeDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // Colleges data
        $colleges = [
            [
                'name' => 'College of Technology and Engineering',
                'abbreviation' => 'COTE',
                'description' => 'Technology and engineering programs',
            ],
            [
                'name' => 'College of Teacher Education',
                'abbreviation' => 'CTE',
                'description' => 'Education and teacher training programs',
            ],
            [
                'name' => 'College of Maritime Education',
                'abbreviation' => 'COMED',
                'description' => 'Maritime and Nautical programs',
            ],
            [
                'name' => 'College of Arts and Sciences',
                'abbreviation' => 'CAS',
                'description' => 'Arts and Sciences programs',
            ],
            [
                'name' => 'College of Graduate Studies',
                'abbreviation' => 'CGS',
                'description' => 'Doctoral and Masteral programs',
            ],
            [
                'name' => 'PIT High School',
                'abbreviation' => 'PIT-HS',
                'description' => 'Junior high school grade levels',
            ],
        ];

        $collegeMap = [];

        foreach ($colleges as $college) {
            DB::table('colleges')->updateOrInsert(
                ['name' => $college['name']],
                [
                    'abbreviation' => $college['abbreviation'],
                    'description' => $college['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $collegeMap[$college['name']] = DB::table('colleges')
                ->where('name', $college['name'])
                ->value('id');
        }

        // Departments data
        $departments = [
            // College of Technology and Engineering
            ['college_name' => 'College of Technology and Engineering', 'name' => 'Information Technology'],
            ['college_name' => 'College of Technology and Engineering', 'name' => 'Electrical Engineering'],
            ['college_name' => 'College of Technology and Engineering', 'name' => 'Mechanical Engineering'],
            ['college_name' => 'College of Technology and Engineering', 'name' => 'Industrial Engineering'],
            ['college_name' => 'College of Technology and Engineering', 'name' => 'Industrial Technology'],

            // College of Teacher Education
            ['college_name' => 'College of Teacher Education', 'name' => 'Elementary Education'],
            ['college_name' => 'College of Teacher Education', 'name' => 'Secondary Education'],
            ['college_name' => 'College of Teacher Education', 'name' => 'Social Science Education'],
            ['college_name' => 'College of Teacher Education', 'name' => 'Technical-Vocational Teacher Education'],

            // College of Maritime Education
            ['college_name' => 'College of Maritime Education', 'name' => 'Marine Engineering'],
            ['college_name' => 'College of Maritime Education', 'name' => 'Marine Transportation'],

            // College of Arts and Sciences
            ['college_name' => 'College of Arts and Sciences', 'name' => 'Language and Literature'],
            ['college_name' => 'College of Arts and Sciences', 'name' => 'Mathematics and Science'],
            ['college_name' => 'College of Arts and Sciences', 'name' => 'Social Sciences'],
            ['college_name' => 'College of Arts and Sciences', 'name' => 'Business Administration'],
            ['college_name' => 'College of Arts and Sciences', 'name' => 'Communication'],
            ['college_name' => 'College of Arts and Sciences', 'name' => 'Marine Biology'],
            ['college_name' => 'College of Arts and Sciences', 'name' => 'Hospitality Management'],

            // College of Graduate Studies
            ['college_name' => 'College of Graduate Studies', 'name' => 'Doctoral Programs'],
            ['college_name' => 'College of Graduate Studies', 'name' => 'Masteral Programs'],
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->updateOrInsert(
                [
                    'college_id' => $collegeMap[$dept['college_name']],
                    'name' => $dept['name'],
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $programs = [
            'College of Teacher Education' => [
                'Bachelor of Secondary Education Mathematics',
                'Bachelor of Secondary Education Social Studies',
                'Bachelor of Technical Vocational Teacher Education',
                'Bachelor of Technology and Livelihood Education',
                'Bachelor of Elementary Education',
                'Bachelor of Secondary Education - English',
                'Bachelor of Secondary Education Filipino',
                'Bachelor of Physical Education',
            ],
            'College of Technology and Engineering' => [
                'Bachelor of Science in Electrical Engineering',
                'Bachelor of Science in Industrial Engineering',
                'Bachelor of Science in Information Technology',
                'Bachelor of Science in Mechanical Engineering',
                [
                    'name' => 'Bachelor of Industrial Technology (Bachelor of Science in Industrial Technology)',
                    'majors' => [
                        'Apparel & Fashion Technology',
                        'Architectural Drafting Technology',
                        'Automotive Technology',
                        'Culinary Technology',
                        'Electrical Technology',
                        'Electronics Technology',
                        'Heating, Ventilating, Air Conditioning and Refrigeration Technology',
                        'Mechanical Technology',
                        'Power Plant Technology',
                        'Welding & Fabrication Technology',
                    ],
                ],
            ],
            'College of Arts and Sciences' => [
                'Bachelor of Arts in Communication',
                'Bachelor of Science in Business Administration',
                'Bachelor of Science in Hospitality Management',
                'Bachelor of Science in Marine Biology',
            ],
            'College of Maritime Education' => [
                'Bachelor of Science in Marine Engineering (Marine)',
                'Bachelor of Science in Marine Transportation (Nautical)',
            ],
            'College of Graduate Studies' => [
                'Doctor of Management',
                'Doctor of Philosophy in Education Major in Educational Management',
                'Doctor of Philosophy in Technology Management',
                'Master of Arts in Education Major in English',
                'Master of Arts in Education Major in Filipino',
                'Master of Arts in Education Major in Physical Education',
                'Master of Arts in Education Major in Educational Management (MAED-EM)',
                'Master of Arts in Education Major in Elementary Education',
                'Master of Arts in Education Major in Technology Education',
                'Master of Arts in Industrial Education',
                'Master of Management (Case Study)',
            ],
        ];

        foreach ($programs as $collegeName => $collegePrograms) {
            foreach ($collegePrograms as $program) {
                $programData = is_array($program)
                    ? $program
                    : ['name' => $program, 'majors' => null];

                DB::table('academic_programs')->updateOrInsert(
                    [
                        'college_id' => $collegeMap[$collegeName],
                        'name' => $programData['name'],
                    ],
                    [
                        'majors' => $programData['majors'] === null
                            ? null
                            : json_encode($programData['majors'], JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}