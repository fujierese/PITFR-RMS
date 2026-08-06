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
        if (!Schema::hasTable('facility_requests')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE facility_requests MODIFY status ENUM('pending','approved','rejected','needs_reschedule') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE facility_requests MODIFY venue_status ENUM('pending','approved','rejected','needs_reschedule') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE facility_requests MODIFY equipment_status ENUM('pending','approved','rejected','needs_reschedule') NOT NULL DEFAULT 'pending'");
            return;
        }

        if ($driver === 'sqlite') {
            $columns = DB::select('PRAGMA table_info(facility_requests)');
            if (empty($columns)) {
                return;
            }

            $columnNames = array_map(static fn ($column) => $column->name, $columns);
            $columnNames = array_values(array_filter($columnNames, static fn ($name) => $name !== 'id'));

            DB::statement('CREATE TABLE facility_requests_new (id INTEGER PRIMARY KEY AUTOINCREMENT)');
            Schema::table('facility_requests_new', function ($table) use ($columnNames): void {
                foreach ($columnNames as $columnName) {
                    $isStatusColumn = in_array($columnName, ['status', 'venue_status', 'equipment_status'], true);
                    if ($isStatusColumn) {
                        $table->text($columnName)->nullable(false)->default('pending');
                        continue;
                    }

                    if (in_array($columnName, ['created_at', 'updated_at', 'deleted_at', 'approved_date', 'equipment_returned_date'], true)) {
                        $table->timestamp($columnName)->nullable();
                        continue;
                    }

                    if (in_array($columnName, ['expected_participants'], true)) {
                        $table->string($columnName, 20)->nullable();
                        continue;
                    }

                    if (in_array($columnName, ['requesting_date', 'requesting_end_date', 'date_requested', 'start_date', 'end_date'], true)) {
                        $table->date($columnName)->nullable();
                        continue;
                    }

                    if (in_array($columnName, ['start_time', 'end_time', 'time'], true)) {
                        $table->string($columnName, 20)->nullable();
                        continue;
                    }

                    if (in_array($columnName, ['venue', 'equipment', 'equipment_quantities', 'equipment_custodian_statuses', 'approval_signature_meta', 'equipment_returned_items'], true)) {
                        $table->text($columnName)->nullable();
                        continue;
                    }

                    if (in_array($columnName, ['requested_by_id'], true)) {
                        $table->unsignedBigInteger($columnName)->nullable();
                        continue;
                    }

                    $table->text($columnName)->nullable();
                }
            });

            $selectList = array_map(static fn ($name) => '"' . str_replace('"', '""', $name) . '"', $columnNames);
            $selectListSql = implode(', ', $selectList);
            DB::statement('INSERT INTO facility_requests_new (' . $selectListSql . ') SELECT ' . $selectListSql . ' FROM facility_requests');
            DB::statement('DROP TABLE facility_requests');
            DB::statement('ALTER TABLE facility_requests_new RENAME TO facility_requests');
            return;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for safety; the application can be re-migrated from scratch as needed.
    }
};
