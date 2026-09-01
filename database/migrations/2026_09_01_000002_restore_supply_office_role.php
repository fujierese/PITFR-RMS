<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('student','faculty','requestor','custodian','custodian-venue','custodian-equipment','admin','supply_office') NOT NULL DEFAULT 'requestor'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('student','faculty','requestor','custodian','custodian-venue','custodian-equipment','admin') NOT NULL DEFAULT 'requestor'");
    }
};
