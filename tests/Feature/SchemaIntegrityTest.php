<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_workflow_tables_and_columns_exist(): void
    {
        foreach (['users', 'facility_requests', 'venues', 'equipment', 'request_venues', 'request_equipment', 'request_histories', 'request_status_history'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('users', [
            'username', 'password', 'role', 'requestor_type', 'school_id_number',
            'faculty_id', 'department', 'college_id', 'department_id', 'position',
            'office_or_organization',
        ]));
        $this->assertTrue(Schema::hasColumns('facility_requests', [
            'requested_by_id', 'status', 'venue_status', 'equipment_status',
            'approved_by_id', 'approved_date', 'equipment_custodian_statuses',
            'priority', 'is_emergency', 'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('request_venues', ['facility_request_id', 'venue_id', 'name', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('request_equipment', ['facility_request_id', 'equipment_id', 'name', 'quantity', 'deleted_at']));
    }

    public function test_workflow_status_and_history_columns_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('facility_requests', [
            'status', 'venue_status', 'equipment_status', 'approved_by_id',
        ]));
        $this->assertTrue(Schema::hasColumns('request_histories', [
            'facility_request_id', 'user_id', 'action', 'occurred_at',
        ]));
        $this->assertTrue(Schema::hasColumns('request_status_history', [
            'facility_request_id', 'status', 'detail', 'created_at',
        ]));
    }

    public function test_student_organization_context_uses_relational_columns_only(): void
    {
        $this->assertTrue(Schema::hasTable('student_organizations'));
        $this->assertTrue(Schema::hasColumn('student_organizations', 'college_id'));
        $this->assertTrue(Schema::hasColumn('student_organizations', 'department_id'));
        $this->assertFalse(Schema::hasColumn('student_organizations', 'college_program'));
        $this->assertFalse(Schema::hasColumn('student_organizations', 'department'));
    }
}