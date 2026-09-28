<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equipment')) {
            DB::table('equipment')
                ->whereRaw('LOWER(name) = ?', ['aircon'])
                ->update(['is_active' => false]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('equipment')) {
            DB::table('equipment')
                ->whereRaw('LOWER(name) = ?', ['aircon'])
                ->update(['is_active' => true]);
        }
    }
};
