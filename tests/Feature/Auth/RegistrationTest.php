<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public sign-up is deliberately closed (QA H6): on an internal HR system,
 * accounts are created by HR or an administrator, never by whoever finds the
 * URL. These tests previously asserted the opposite and were rewritten when
 * the routes were withdrawn.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_screen_is_not_reachable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_an_account_cannot_be_created_from_the_public_route(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_the_login_page_no_longer_offers_a_register_link(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Register');
    }
}
