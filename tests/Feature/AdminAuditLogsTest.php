<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FacilityRequest;
use App\Models\RequestHistory;
use App\Models\User;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_page_filters_request_and_user_audit_entries_by_user_and_shows_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Audit Administrator']);
        $targetUser = User::factory()->create(['role' => 'requestor', 'name' => 'Target Requestor']);
        $otherUser = User::factory()->create(['role' => 'requestor', 'name' => 'Other Requestor']);

        $request = FacilityRequest::factory()->create([
            'control_number' => 'FER-AUDIT-USER-001',
            'requested_by_id' => $targetUser->id,
        ]);
        RequestHistory::create([
            'facility_request_id' => $request->id,
            'user_id' => $targetUser->id,
            'action' => 'cancelled',
            'detail' => 'Cancelled by selected user.',
            'occurred_at' => now(),
        ]);
        AuditLog::create([
            'actor_id' => $admin->id,
            'target_user_id' => $targetUser->id,
            'action' => 'user_updated',
            'details' => 'Updated selected user account.',
            'old_values' => ['department' => 'Old Department'],
            'new_values' => ['department' => 'New Department'],
        ]);
        AuditLog::create([
            'actor_id' => $otherUser->id,
            'target_user_id' => $otherUser->id,
            'action' => 'user_updated',
            'details' => 'Updated unrelated user account.',
            'old_values' => ['department' => 'Other Old'],
            'new_values' => ['department' => 'Other New'],
        ]);

        $response = $this->actingAs($admin)->get(route('supply-office.audit-logs', ['user_id' => $targetUser->id]));

        $response->assertOk()
            ->assertSee('All Users')
            ->assertSee('Target Requestor')
            ->assertSee('FER-AUDIT-USER-001')
            ->assertSee('Cancelled by selected user.')
            ->assertSee('Updated selected user account.')
            ->assertSee('Old Department')
            ->assertSee('New Department')
            ->assertDontSee('Updated unrelated user account.')
            ->assertSee('matching audit entries')
            ->assertSee('Download CSV')
            ->assertSee('href="' . route('supply-office.index') . '"', false)
            ->assertDontSee('href="' . route('requestor.index') . '"', false);
    }

    public function test_audit_logs_are_paginated_and_csv_export_preserves_active_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = User::factory()->create(['role' => 'requestor']);

        for ($index = 0; $index < 55; $index++) {
            AuditLog::create([
                'actor_id' => $actor->id,
                'target_user_id' => $actor->id,
                'action' => 'user_updated',
                'details' => 'Filtered audit entry ' . $index,
                'old_values' => ['department' => 'Before'],
                'new_values' => ['department' => 'After'],
            ]);
        }

        $this->actingAs($admin)
            ->get(route('supply-office.audit-logs', ['user_id' => $actor->id]))
            ->assertOk()
            ->assertSeeText('50 of 55 matching audit entries')
            ->assertSeeText('Page 1 of 2')
            ->assertSee('page=2');

        $response = $this->get(route('supply-office.audit-logs.export', [
            'user_id' => $actor->id,
            'action' => 'user_updated',
        ]));
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Filtered audit entry 0', $csv);
        $this->assertStringContainsString('Filtered audit entry 54', $csv);
        $this->assertSame(55, substr_count($csv, 'user_updated'));
    }

    public function test_audit_log_export_requires_admin_role(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);

        $this->actingAs($requestor)
            ->get(route('supply-office.audit-logs.export'))
            ->assertForbidden();
    }

    public function test_audit_log_page_remains_admin_only_if_route_role_middleware_is_missing(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);

        $this->withoutMiddleware(RoleMiddleware::class)
            ->actingAs($requestor)
            ->get(route('supply-office.audit-logs'))
            ->assertForbidden();
    }

    public function test_audit_log_export_remains_admin_only_if_route_role_middleware_is_missing(): void
    {
        $requestor = User::factory()->create(['role' => 'requestor']);

        $this->withoutMiddleware(RoleMiddleware::class)
            ->actingAs($requestor)
            ->get(route('supply-office.audit-logs.export'))
            ->assertForbidden();
    }

    public function test_custodian_does_not_receive_supply_office_audit_navigation_or_access(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian']);

        $this->actingAs($custodian)
            ->get(route('supply-office.audit-logs'))
            ->assertForbidden();

        $this->view('components.dashboard-sidebar', ['user' => $custodian])
            ->assertDontSee('Audit Logs')
            ->assertSee('Custodian');
    }

    public function test_supply_office_user_receives_supply_office_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->view('components.dashboard-sidebar', ['user' => $admin])
            ->assertSee('Supply Office')
            ->assertSee('Audit Logs')
            ->assertSee(route('supply-office.index'));
    }
}
