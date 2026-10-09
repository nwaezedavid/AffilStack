<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\OpenAIProvider;
use App\Services\Blog\BlogFeed;
use App\Services\Links\LinkCloakingService;
use App\Support\MailText;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression tests for bugs found in the final pre-relaunch QA pass
 * (October 2026).
 */
class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_tracked_links_send_clicks_to_the_affiliate_link(): void
    {
        $offer = $this->offer(['affiliate_link' => 'https://hostinger.com?REFERRALCODE=ADA']);

        $link = app(LinkCloakingService::class)->getOrCreateForOffer($offer, 'blog');

        $this->assertSame('https://hostinger.com?REFERRALCODE=ADA', $link->destination_url);
    }

    public function test_links_made_before_the_affiliate_link_was_added_follow_it(): void
    {
        $offer = $this->offer(['affiliate_link' => null]);
        $link = app(LinkCloakingService::class)->getOrCreateForOffer($offer, 'blog');
        $this->assertSame($offer->product_url, $link->destination_url);

        $offer->update(['affiliate_link' => 'https://hostinger.com?REFERRALCODE=ADA']);

        $this->assertSame('https://hostinger.com?REFERRALCODE=ADA', $link->fresh()->destination_url);
    }

    public function test_a_link_pointed_somewhere_else_by_hand_is_left_alone(): void
    {
        $offer = $this->offer(['affiliate_link' => 'https://hostinger.com?REFERRALCODE=ADA']);
        $link = app(LinkCloakingService::class)->getOrCreateForOffer($offer, 'blog');
        $link->update(['destination_url' => 'https://my-landing-page.example']);

        $offer->update(['affiliate_link' => 'https://hostinger.com?REFERRALCODE=NEW']);

        $this->assertSame('https://my-landing-page.example', $link->fresh()->destination_url);
    }

    public function test_a_completely_blank_crm_contact_is_refused(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post('/crm', [])->assertSessionHasErrors('name');
        $this->assertSame(0, CrmContact::count());

        $this->actingAs($user)->post('/crm', ['email' => 'buyer@example.com'])->assertSessionHasNoErrors();
        $this->assertSame(1, CrmContact::count());
    }

    public function test_ai_provider_errors_are_not_shown_to_customers(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided: sk-secret']], 401)]);

        $provider = new OpenAIProvider('sk-secret', 'https://api.openai.com/v1', 'gpt-test', 'gpt-image-test', 10);

        try {
            $provider->generateJson('system', 'user');
            $this->fail('Expected an AI generation exception.');
        } catch (AIGenerationException $e) {
            $this->assertSame(AIGenerationException::UNAVAILABLE, $e->getMessage());
            $this->assertStringNotContainsString('sk-secret', $e->getMessage());
        }
    }

    public function test_mail_text_escaping_stops_links_but_keeps_ordinary_text_readable(): void
    {
        $this->assertSame('john_doe@example.com (CEO) 3*4', MailText::escape('john_doe@example.com (CEO) 3*4'));
        $this->assertSame('\\[click\\](https://evil.example) \\!\\[\\](x)', MailText::escape('[click](https://evil.example) ![](x)'));
    }

    public function test_a_redirecting_blog_address_explains_where_it_redirects(): void
    {
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['93.184.216.34']);
        Http::fake(['*' => Http::response('', 301, ['Location' => 'https://www.blog.example/wp-json/wp/v2/posts?per_page=3'])]);

        try {
            app(BlogFeed::class)->fetch('https://blog.example', 3);
            $this->fail('Expected the blog fetch to fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('redirects to https://www.blog.example', $e->getMessage());
        } finally {
            OutboundUrlGuard::resolveUsing(null);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function offer(array $attributes = []): Offer
    {
        return Offer::create(array_merge([
            'user_id' => $this->customer()->id,
            'product_name' => 'Hostinger',
            'product_url' => 'https://www.hostinger.com',
            'affiliate_network' => 'Impact',
            'status' => 'ready',
        ], $attributes));
    }

    protected function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }
}
