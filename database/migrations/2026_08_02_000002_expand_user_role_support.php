<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('student','faculty','requestor','custodian','custodian-venue','custodian-equipment','admin') NOT NULL DEFAULT 'requestor'");
            return;
        }

        if ($driver === 'sqlite') {
            $columns = DB::select('PRAGMA table_info(users)');
            if (empty($columns)) {
                return;
            }

            $hasRoleColumn = false;
            foreach ($columns as $column) {
                if ($column->name === 'role') {
                    $hasRoleColumn = true;
                    break;
                }
            }

            if (!$hasRoleColumn) {
                return;
            }

            Schema::table('users', function ($table): void {
                $table->string('role_temp')->nullable();
            });

            DB::statement('UPDATE users SET role_temp = role');
            DB::statement('ALTER TABLE users DROP COLUMN role');
            Schema::table('users', function ($table): void {
                $table->string('role')->nullable();
            });
            DB::statement('UPDATE users SET role = role_temp');
            Schema::table('users', function ($table): void {
                $table->dropColumn('role_temp');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for safety.
    }
};
