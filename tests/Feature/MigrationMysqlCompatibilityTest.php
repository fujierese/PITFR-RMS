<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Live server: `migrate` failed with "Identifier name ... is too long" (MySQL allows 64
 * characters; SQLite used by the tests allows more). Every index and foreign key name
 * must fit MySQL, and a table left half-created by that failure must be completed.
 */
class MigrationMysqlCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_index_and_foreign_key_name_fits_mysql(): void
    {
        $tooLong = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                if (strlen((string) $index['name']) > 64) {
                    $tooLong[] = $table . ': ' . $index['name'];
                }
            }
            foreach (Schema::getForeignKeys($table) as $key) {
                if ($key['name'] !== null && strlen((string) $key['name']) > 64) {
                    $tooLong[] = $table . ': ' . $key['name'];
                }
            }
        }

        $this->assertSame([], $tooLong);
    }

    public function test_the_conflicts_migration_completes_a_half_created_table(): void
    {
        Schema::table('reservation_conflicts', function ($table): void {
            $table->dropUnique('res_conflicts_pair_unique');
            $table->dropIndex('res_conflicts_status_affected_index');
        });

        $migration = require database_path('migrations/2026_11_24_000002_create_reservation_conflicts_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex('reservation_conflicts', 'res_conflicts_pair_unique'));
        $this->assertTrue(Schema::hasIndex('reservation_conflicts', 'res_conflicts_status_affected_index'));
    }
}
