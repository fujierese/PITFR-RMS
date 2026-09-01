<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['username' => 'student1@gmail.com',   'name' => 'BITS ORG',               'role' => 'requestor',             'requestor_type' => 'student', 'password' => Hash::make('password')],
            ['username' => 'faculty1@gmail.com',   'name' => 'IT Department',           'role' => 'requestor',             'requestor_type' => 'faculty', 'password' => Hash::make('password')],
            ['username' => 'mmercado@gmail.com',   'name' => 'MILDRED P. MERCADO',      'role' => 'custodian',             'password' => Hash::make('password')],
            ['username' => 'asala@gmail.com',      'name' => 'ARLENE L. SALA',          'role' => 'custodian',             'password' => Hash::make('password')],
            ['username' => 'ctado@gmail.com',      'name' => 'CHARLES ROMMEL L. TADO', 'role' => 'custodian',             'password' => Hash::make('password')],
            ['username' => 'rguillemer@gmail.com', 'name' => 'ROGELIO GUILLEMER',       'role' => 'custodian',             'password' => Hash::make('password')],
            ['username' => 'jsuralta@gmail.com',   'name' => 'JAIME SURALTA',           'role' => 'custodian',             'password' => Hash::make('password')],
            ['username' => 'lalmerino@gmail.com',  'name' => 'L. ALMERINO',             'role' => 'custodian',             'password' => Hash::make('password')],
            ['username' => 'jrvillas@gmail.com',   'name' => 'JR. VILLAS',              'role' => 'custodian',             'password' => Hash::make('password')],
            ['username' => 'admin',                'name' => 'Administrator',           'role' => 'admin',                 'password' => Hash::make('admin')],
        ];

        $columns = Schema::getColumnListing('users');

        foreach ($users as $user) {
            $payload = $user;
            if (!in_array('requestor_type', $columns, true)) {
                unset($payload['requestor_type']);
            }

            User::updateOrCreate(
                ['username' => $user['username']],
                $payload,
            );
        }
    }
}