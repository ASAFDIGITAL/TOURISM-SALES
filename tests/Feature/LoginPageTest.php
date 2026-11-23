<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_page_defaults_to_hebrew_with_open_sans_and_split_hero_image(): void
    {
        $response = $this->get('/agent/login');

        $response->assertOk();
        $response->assertSee('lang="he"', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('Open+Sans', false);
        $response->assertSee('images/login-hero.jpg', false);
        $response->assertSee('images/logo-light.png', false);
        $response->assertSee('images/logo-dark.png', false);
        $response->assertSee('התחבר לחשבון שלך');
        $response->assertSee('עברית');
        $response->assertSee('العربية');
        $response->assertSee('English');
    }

    public function test_guest_can_switch_language_on_login_page(): void
    {
        $this->from('/agent/login')
            ->post(route('locale.switch', 'en'))
            ->assertRedirect('/agent/login')
            ->assertSessionHas('locale', 'en');

        $response = $this->get('/agent/login');
        $response->assertOk();
        $response->assertSee('lang="en"', false);
        $response->assertSee('Sign in');

        $this->from('/agent/login')
            ->post(route('locale.switch', 'ar'))
            ->assertRedirect('/agent/login')
            ->assertSessionHas('locale', 'ar');

        $responseAr = $this->get('/agent/login');
        $responseAr->assertOk();
        $responseAr->assertSee('lang="ar"', false);
        $responseAr->assertSee('تسجيل الدخول');
    }

    public function test_invalid_locale_returns_404(): void
    {
        $this->post('/locale/fr')->assertNotFound();
    }
}
