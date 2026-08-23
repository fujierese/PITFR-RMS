<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guest_landing_page_does_not_repeat_calendar_explanations(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('aria-label="Guest navigation"', false)
            ->assertSee('href="#calendar-section"', false)
            ->assertSee('href="#features"', false)
            ->assertSee('href="#how-it-works"', false)
            ->assertSee('View Facility Calendar')
            ->assertDontSee('Public Facility Availability')
            ->assertDontSee('How to Request')
            ->assertDontSee('Availability overview');
    }

    public function test_login_page_uses_email_and_home_return_link(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('Email')
            ->assertSee('Forgot Password?')
            ->assertSee('Return to Home')
            ->assertDontSee('Username')
            ->assertDontSee('Continue as Guest');
    }
}
