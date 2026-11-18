<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicInformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_and_privacy_information_pages_are_publicly_accessible(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Frequently Asked Questions')
            ->assertSee('Does an urgent request skip approval?');

        $this->get(route('privacy.data-protection'))
            ->assertOk()
            ->assertSee('Privacy &amp; Data Protection', false)
            ->assertSee('A. Privacy Notice')
            ->assertSee('B. Purpose of Data Collection')
            ->assertSee('C. Data Protection')
            ->assertSee('D. Data Privacy Rights')
            ->assertSee('Republic Act No. 10173')
            ->assertDontSee('Data Protection Officer')
            ->assertDontSee('To be completed by PIT');

        // The old addresses lead to the same page.
        $this->get('/privacy-policy')->assertRedirect('/privacy-and-data-protection');
        $this->get('/data-privacy-act')->assertRedirect('/privacy-and-data-protection#data-privacy-rights');
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

    public function test_privacy_page_is_linked_for_guests_and_every_role(): void
    {
        $link = 'href="' . route('privacy.data-protection') . '"';
        $this->get(route('home'))->assertOk()->assertSee($link, false);

        foreach (['requestor', 'custodian-venue', 'custodian-equipment', 'admin'] as $role) {
            $user = \App\Models\User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->get($user->getDashboardRoute())
                ->assertOk()
                ->assertSee($link, false);
        }
    }
}
