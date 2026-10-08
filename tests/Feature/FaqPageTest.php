<?php

namespace Tests\Feature;

use App\Models\FaqItem;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dedicated FAQ page (/faq) and the Help Centre (/help) that links to it.
 */
class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_faq_page_lists_published_questions_by_category_with_faq_schema(): void
    {
        FaqItem::create(['question' => 'How do I cancel my plan?', 'answer' => 'From Billing.', 'category' => 'billing', 'sort_order' => 1, 'is_published' => true]);
        FaqItem::create(['question' => 'Can I research any product?', 'answer' => 'Yes.', 'category' => 'research', 'sort_order' => 2, 'is_published' => true]);
        FaqItem::create(['question' => 'Hidden question', 'answer' => 'No.', 'category' => 'billing', 'sort_order' => 3, 'is_published' => false]);

        $response = $this->get('/faq')->assertOk();

        $response->assertSee('How do I cancel my plan?')
            ->assertSee('Can I research any product?')
            ->assertDontSee('Hidden question')
            ->assertSee('id="faq-billing"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"How do I cancel my plan?"', false)
            ->assertSee('data-faq-search', false);
    }

    public function test_faq_page_copy_is_editable(): void
    {
        SiteSetting::set('faq_heading', 'Got questions?');

        $this->get('/faq')->assertSee('Got questions?');
    }

    public function test_the_help_centre_links_to_the_faq_and_shows_popular_questions_without_schema(): void
    {
        FaqItem::create(['question' => 'How do I cancel my plan?', 'answer' => 'From Billing.', 'category' => 'billing', 'sort_order' => 1, 'is_published' => true]);

        $this->get('/help')
            ->assertOk()
            ->assertSee('href="'.route('faq.index').'"', false)
            ->assertSee('How do I cancel my plan?')
            ->assertSee(route('support.chat'), false)
            ->assertDontSee('"@type":"FAQPage"', false);
    }
}
