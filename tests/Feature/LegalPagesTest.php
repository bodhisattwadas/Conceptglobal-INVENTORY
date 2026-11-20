<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_privacy_policy_page(): void
    {
        $response = $this->get(route('privacy-policy'));

        $response->assertOk();
        $response->assertSee('Privacy Policy');
        $response->assertSee('Glam &amp; Glow', false);
        $response->assertSee('20 November 2026');
        $response->assertSee('+91 90517 15322');
        $response->assertSee('Baranagar, Kolkata');
        $response->assertSee('Let\'s Make Baranagar Beautiful!', false);
    }

    public function test_guest_can_access_terms_and_conditions_page(): void
    {
        $response = $this->get(route('terms-and-conditions'));

        $response->assertOk();
        $response->assertSee('Terms &amp; Conditions', false);
        $response->assertSee('Glam &amp; Glow', false);
        $response->assertSee('20 November 2026');
        $response->assertSee('Beauty Parlour Services and Appointments');
        $response->assertSee('+91 90517 15322');
        $response->assertSee('Baranagar, Kolkata');
        $response->assertSee('Let\'s Make Baranagar Beautiful!', false);
    }

    public function test_privacy_and_terms_shorthand_routes_redirect_correctly(): void
    {
        $this->get('/privacy')->assertRedirect(route('privacy-policy'));
        $this->get('/terms')->assertRedirect(route('terms-and-conditions'));
    }

    public function test_authenticated_user_can_view_legal_pages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('privacy-policy'))
            ->assertOk()
            ->assertSee('Dashboard');

        $this->actingAs($user)
            ->get(route('terms-and-conditions'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_footer_contains_links_to_both_legal_pages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('privacy-policy'));
        $response->assertSee(route('terms-and-conditions'));
    }

    public function test_login_page_contains_links_to_both_legal_pages(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('privacy-policy'));
        $response->assertSee(route('terms-and-conditions'));
    }
}
