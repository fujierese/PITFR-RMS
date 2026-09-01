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
            ->assertSeeInOrder([
                'Home',
                'View Facility Calendar',
                'About Us',
                'System Features',
                'How It Works',
                'Contact Us',
                'Login to Request',
            ])
            ->assertSee('href="#calendar-section"', false)
            ->assertSee('About Us')
            ->assertSee('href="#features"', false)
            ->assertSee('href="#how-it-works"', false)
            ->assertSee('PIT Facility &amp; Equipment Request System', false)
            ->assertSee('href="' . route('login') . '"', false)
            ->assertSee('href="' . route('register') . '"', false)
            ->assertSee('View Facility Calendar')
            ->assertSee('Request Validation')
            ->assertSee('Supply Office Review')
            ->assertSee('hours, days, or months depending on the queue')
            ->assertSee('New users can create an account to get started.')
            ->assertDontSee('Smart Scheduling')
            ->assertDontSee('Role-Based Access')
            ->assertDontSee('>PITFR</a>', false)
            ->assertDontSee('Custodian')
            ->assertDontSee('administration office')
            ->assertDontSee('Administrator Approval')
            ->assertDontSee('Admin Approval')
            ->assertDontSee('Admin validation')
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
