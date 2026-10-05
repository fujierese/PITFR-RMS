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

        $this->get(route('privacy.policy'))
            ->assertOk()
            ->assertSee('Draft — not an approved institutional policy.')
            ->assertSee('[To be confirmed by PIT]');

        $this->get(route('privacy.data-act'))
            ->assertOk()
            ->assertSee('Republic Act No. 10173')
            ->assertSee('not legal advice');
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

    public function test_all_guest_information_links_open_the_shared_modal_instead_of_navigating(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-open-info-modal="privacy"', false)
            ->assertSee('data-open-info-modal="data-privacy"', false)
            ->assertDontSee('href="' . route('privacy.policy') . '"', false)
            ->assertDontSee('href="' . route('privacy.data-act') . '"', false)
            ->assertSee('id="info-modal-title"', false)
            ->assertSee('data-info-modal-panel="privacy"', false)
            ->assertSee('data-info-modal-panel="data-privacy"', false);

        $user = \App\Models\User::factory()->create(['role' => 'requestor']);
        $this->actingAs($user)
            ->get(route('requestor.index'))
            ->assertOk()
            ->assertSee('data-open-info-modal="privacy"', false)
            ->assertSee('data-open-info-modal="data-privacy"', false)
            ->assertDontSee('href="' . route('privacy.policy') . '"', false)
            ->assertDontSee('href="' . route('privacy.data-act') . '"', false);
    }
}
