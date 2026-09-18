<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The redesigned public About page (task #161): a real, structured company
 * page (mission, vision, goals, founder, who we serve, how we work, what
 * we do, a closing CTA) driven by admin-editable about_* SiteSetting keys
 * (Site > About Page) rather than the old generic SitePage rich-text body.
 * See resources/views/marketing/about.blade.php.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_shows_real_default_content_when_nothing_is_configured(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Our story');
        $response->assertSee('Our mission');
        $response->assertSee('What we do');
        $response->assertSee('How we work');
        $response->assertSee('Who we serve');
        $response->assertSee('See plans &amp; pricing', false);
    }

    public function test_about_page_never_shows_a_fabricated_founder(): void
    {
        $this->get('/about')->assertDontSee('Behind');
    }

    public function test_about_page_shows_the_founder_once_a_real_name_is_entered(): void
    {
        SiteSetting::set('about_founder_name', 'Ada Lovelace');
        SiteSetting::set('about_founder_role', 'Founder & CEO');
        SiteSetting::set('about_founder_bio', 'Started the company after years in affiliate marketing.');

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Ada Lovelace');
        $response->assertSee('Founder & CEO');
    }

    public function test_admin_configured_content_overrides_the_defaults(): void
    {
        SiteSetting::set('about_mission', 'Our very own custom mission statement.');
        SiteSetting::set('about_goals', json_encode([
            ['icon' => '🏆', 'title' => 'Custom goal', 'description' => 'A goal only this admin entered.'],
        ]));

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Our very own custom mission statement.');
        $response->assertSee('Custom goal');
    }
}
