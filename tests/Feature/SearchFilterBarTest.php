<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up fixes: every list page uses the shared <x-search-filter-bar>. The Advanced
 * Filters panel expands inline (never an absolute/floating overlay), keeps the selected
 * values, and Clear keeps the page's own status (e.g. the sidebar's Pending list).
 */
class SearchFilterBarTest extends TestCase
{
    use RefreshDatabase;

    private function assertSharedBar(string $html): void
    {
        $this->assertStringContainsString('data-search-filter-bar', $html);
        $this->assertStringNotContainsString('absolute right-0 z-10', $html, 'Filters must not float over the list.');
        $this->assertStringNotContainsString('>Apply Filters<', $html);
    }

    public function test_custodian_lists_use_the_inline_bar_and_keep_the_status(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian-venue']);
        Venue::create(['name' => 'Covered Court', 'capacity' => 300, 'custodian_id' => $custodian->id, 'is_active' => true]);

        foreach (['pending', 'approved', 'all'] as $filter) {
            $html = $this->actingAs($custodian)->get(route('custodian.index', ['filter' => $filter, 'sort' => 'latest']))->assertOk()->getContent();
            $this->assertSharedBar($html);
            $this->assertStringContainsString('<input type="hidden" name="filter" value="' . $filter . '">', $html);
            $this->assertMatchesRegularExpression('/<option value="latest"\s+selected/', $html);
            $this->assertStringContainsString('aria-expanded="true"', $html, 'A non-default filter opens the panel.');
            $this->assertStringContainsString('Earliest reservation first', $html);
        }

        $equipmentCustodian = User::factory()->create(['role' => 'custodian-equipment']);
        $this->assertSharedBar($this->actingAs($equipmentCustodian)->get(route('custodian.index', ['filter' => 'pending']))->assertOk()->getContent());
    }

    public function test_supply_office_and_admin_pages_use_the_shared_bar(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => config('pitfr.final_approver_username') ?: 'asala@gmail.com']);

        foreach ([
            route('supply-office.index'),
            route('supply-office.requests.pending'),
            route('supply-office.requests.approved'),
            route('supply-office.requests.needs-reschedule'),
            route('supply-office.requests.final-approval'),
            route('supply-office.requests.rejected'),
            route('supply-office.requests.returns'),
            route('admin.users'),
            route('supply-office.venues.index'),
            route('supply-office.equipment.index'),
        ] as $url) {
            $this->assertSharedBar($this->actingAs($admin)->get($url)->assertOk()->getContent());
        }

        $html = $this->actingAs($admin)->get(route('supply-office.requests.pending', ['review' => 'venue_pending', 'search' => 'gym']))->assertOk()->getContent();
        $this->assertStringContainsString('value="gym"', $html);
        $this->assertMatchesRegularExpression('/<option value="venue_pending"\s+selected/', $html);
        $this->assertStringContainsString('<optgroup label="Venue review">', $html);
    }

    public function test_igp_and_history_pages_use_the_shared_bar(): void
    {
        $igp = User::factory()->create(['role' => 'igp']);
        $this->assertSharedBar($this->actingAs($igp)->get(route('igp.index', ['search' => 'x']))->assertOk()->getContent());

        $requestor = User::factory()->create(['role' => 'requestor', 'requestor_type' => 'student']);
        $html = $this->actingAs($requestor)->get(route('history.index', ['sort' => 'oldest']))->assertOk()->getContent();
        $this->assertSharedBar($html);
        $this->assertMatchesRegularExpression('/<option value="oldest"\s+selected/', $html);
    }
}
