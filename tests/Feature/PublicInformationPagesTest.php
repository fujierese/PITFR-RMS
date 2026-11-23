<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicInformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_is_public_and_the_privacy_page_is_hidden(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Frequently Asked Questions')
            ->assertSee('Does an urgent request skip approval?')
            ->assertDontSee('Privacy Policy draft');

        // Hidden at the client's request (no official privacy policy yet).
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('privacy.data-protection'));
        $this->get('/privacy-and-data-protection')->assertNotFound();
        $this->get('/privacy-policy')->assertNotFound();
        $this->get('/data-privacy-act')->assertNotFound();
    }

    public function test_faq_navigation_opens_the_faq_modal_on_public_and_authenticated_layouts(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-open-faq-modal', false)
            ->assertSee('<button type="button" data-open-faq-modal', false)
            ->assertDontSee('<a href="' . route('faq') . '" data-open-faq-modal', false)
            ->assertSee('id="faq-modal"', false)
            ->assertSee('latitude=11.0508&longitude=124.3843', false)
            ->assertSee('Does an urgent request skip approval?');

        $user = \App\Models\User::factory()->create(['role' => 'requestor']);
        $this->actingAs($user)
            ->get(route('requestor.index'))
            ->assertOk()
            ->assertSee('data-open-faq-modal', false)
            ->assertDontSee('<a class="font-medium text-emerald-800 underline-offset-4 hover:underline" href="' . route('faq') . '"', false)
            ->assertSee('id="faq-modal"', false);
    }

    public function test_no_privacy_links_for_guests_or_any_role(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertDontSee('Privacy &amp; Data Protection', false)
            ->assertDontSee('/privacy-and-data-protection', false);

        foreach (['requestor', 'custodian-venue', 'custodian-equipment', 'admin'] as $role) {
            $user = \App\Models\User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->get($user->getDashboardRoute())
                ->assertOk()
                ->assertDontSee('Privacy &amp; Data Protection', false)
                ->assertDontSee('/privacy-and-data-protection', false)
                ->assertDontSee('Data Privacy Act');
        }
    }
}
