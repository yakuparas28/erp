<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_turkish(): void
    {
        $this->get('/login')->assertSee('Giriş Yap');
    }

    public function test_switching_to_english_translates_the_ui(): void
    {
        $this->post('/locale/en')->assertRedirect();

        $this->get('/login')
            ->assertSee('Sign in to ERP')
            ->assertSee('Sign In')
            ->assertDontSee('Giriş Yap');
    }

    public function test_locale_preference_persists_in_session(): void
    {
        $this->post('/locale/en');
        $this->get('/login')->assertSee('Email Address');

        $this->post('/locale/tr');
        $this->get('/login')->assertSee('E-posta Adresi');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->post('/locale/de')->assertNotFound();
    }
}
