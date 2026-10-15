<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home page is the login screen for guests, so "/" redirects there
     * rather than rendering a public landing page.
     */
    public function test_the_home_page_sends_a_guest_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
