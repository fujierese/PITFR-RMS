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
    }
}