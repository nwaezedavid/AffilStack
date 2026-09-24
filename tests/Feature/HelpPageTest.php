<?php

namespace Tests\Feature;

use App\Models\FaqItem;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public /help page (item #1 of the "help/support" request): a visitor
 * must see three direct ways to reach us — email, AI chat, or a ticket —
 * not just a login link, plus the published FAQ list. Chat/ticket links
 * point at auth-gated routes on purpose: a guest clicking them is bounced
 * to login and back (Laravel's default `intended` redirect), which is
 * cheaper and more consistent than duplicating that gate here.
 */
class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_shows_direct_contact_options(): void
    {
        SiteSetting::set('support_email', 'help@example.test');

        $response = $this->get('/help');

        $response->assertOk();
        $response->assertSee('help@example.test');
        $response->assertSee(route('support.chat'), false);
        $response->assertSee(route('support.create'), false);
    }

    public function test_help_page_falls_back_to_a_default_support_email(): void
    {
        $response = $this->get('/help');

        $response->assertOk();
        $response->assertSee('support@affilstack.com');
    }

    public function test_help_page_lists_published_faqs_grouped_by_category(): void
    {
        FaqItem::create([
            'question' => 'How do I cancel my plan?',
            'answer' => 'From Billing settings in your dashboard.',
            'category' => 'billing',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        FaqItem::create([
            'question' => 'Unpublished question',
            'answer' => 'Should not appear.',
            'category' => 'billing',
            'sort_order' => 2,
            'is_published' => false,
        ]);

        $response = $this->get('/help');

        $response->assertOk();
        $response->assertSee('How do I cancel my plan?');
        $response->assertDontSee('Unpublished question');
    }
}
