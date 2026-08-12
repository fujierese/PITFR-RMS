<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenueAndEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('venues')->truncate();
        DB::table('equipment')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        
        DB::table('venues')->insert([
            ['name' => 'Conference Hall & Interaction Center (CHIC)', 'capacity' => 200, 'custodian_id' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gymnasium',                                   'capacity' => 500, 'custodian_id' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Balay Alumni',                                'capacity' => 300, 'custodian_id' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Oval Grounds',                                'capacity' => 1000, 'custodian_id' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Covered Court',                               'capacity' => 150, 'custodian_id' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Volleyball Court',                            'capacity' => 100, 'custodian_id' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('equipment')->insert([
            ['name' => 'Sound System',      'quantity' => 2,   'quantity_available' => 2,   'custodian_id' => 6, 'authorized_custodian_ids' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Microphones',       'quantity' => 10,  'quantity_available' => 10,  'custodian_id' => 7, 'authorized_custodian_ids' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Canopies',          'quantity' => 5,   'quantity_available' => 5,   'custodian_id' => 7, 'authorized_custodian_ids' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Industrial Fans',   'quantity' => 8,   'quantity_available' => 8,   'custodian_id' => 8, 'authorized_custodian_ids' => json_encode([9]), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Iwata Cooler Fans', 'quantity' => 4,   'quantity_available' => 4,   'custodian_id' => 8, 'authorized_custodian_ids' => json_encode([9]), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tables',            'quantity' => 30,  'quantity_available' => 30,  'custodian_id' => 7, 'authorized_custodian_ids' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Monobloc chairs',   'quantity' => 1000, 'quantity_available' => 1000, 'custodian_id' => 7, 'authorized_custodian_ids' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}