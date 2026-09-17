<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\LinkedinReplyDraft;
use App\Models\Offer;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Task #3: exporting generated LinkedIn content for manual posting, and
 * "paste their reply, get an AI-drafted response" — see
 * App\Http\Controllers\Dashboard\LinkedInController::export() and
 * App\Services\Social\LinkedInReplyAssistantService.
 */
class LinkedInExportAndReplyAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create(['credits_balance' => 1000]);
        $this->user->assignRole('user');
        $this->offer = Offer::create([
            'user_id' => $this->user->id, 'product_name' => 'Acme Widget',
            'product_url' => 'https://acme.example/widget', 'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);
    }

    protected function connectLinkedIn(): void
    {
        SocialConnection::create([
            'user_id' => $this->user->id,
            'provider' => 'linkedin',
            'account_name' => 'Jordan Smith',
            'account_id' => 'abc123',
            'connected_at' => now(),
        ]);
    }

    // --- Export ------------------------------------------------------------

    public function test_exporting_a_post_requires_a_connected_linkedin_account_first(): void
    {
        $generation = Generation::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'linkedin_post', 'status' => 'completed', 'output_meta' => ['post_text' => 'Hello world']]);

        $this->actingAs($this->user)
            ->get(route('generations.linkedin.export', $generation))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_exporting_a_post_downloads_the_cloaked_text_once_connected(): void
    {
        $this->connectLinkedIn();
        $generation = Generation::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'linkedin_post', 'status' => 'completed', 'output_meta' => ['post_text' => 'Hello world, check out {{AFFILIATE_LINK}}']]);

        $response = $this->actingAs($this->user)->get(route('generations.linkedin.export', $generation));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('{{AFFILIATE_LINK}}', $response->getContent());
    }

    public function test_exporting_an_article_includes_the_headline(): void
    {
        $this->connectLinkedIn();
        $generation = Generation::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'linkedin_article', 'status' => 'completed', 'output_meta' => ['headline' => 'Why This Works', 'article_markdown' => 'Body text here.']]);

        $response = $this->actingAs($this->user)->get(route('generations.linkedin.export', $generation));

        $response->assertOk();
        $this->assertStringContainsString('Why This Works', $response->getContent());
        $this->assertStringContainsString('Body text here.', $response->getContent());
    }

    public function test_exporting_a_non_linkedin_generation_404s(): void
    {
        $this->connectLinkedIn();
        $generation = Generation::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'blog_article', 'status' => 'completed']);

        $this->actingAs($this->user)
            ->get(route('generations.linkedin.export', $generation))
            ->assertNotFound();
    }

    public function test_a_user_cannot_export_another_users_generation(): void
    {
        $this->connectLinkedIn();
        $generation = Generation::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'linkedin_post', 'status' => 'completed', 'output_meta' => ['post_text' => 'x']]);
        $other = User::factory()->create();
        $other->assignRole('user');

        $this->actingAs($other)
            ->get(route('generations.linkedin.export', $generation))
            ->assertForbidden();
    }

    // --- Reply assistant -----------------------------------------------------

    public function test_drafting_a_reply_spends_credits_and_stores_the_draft(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn(['draft_reply' => 'Thanks for getting back to me! Happy to answer any questions.']);
        });

        $response = $this->actingAs($this->user)
            ->post(route('offers.linkedin.reply-assistant.store', $this->offer), [
                'their_message' => 'Hey, thanks for reaching out — how much does this cost?',
            ]);

        $response->assertRedirect(route('offers.linkedin.reply-assistant', $this->offer));
        $response->assertSessionHas('success');

        $draft = LinkedinReplyDraft::firstOrFail();
        $this->assertSame($this->user->id, $draft->user_id);
        $this->assertSame($this->offer->id, $draft->offer_id);
        $this->assertStringContainsString('Happy to answer', $draft->draft_reply);

        $this->assertSame(1000 - config('credits.costs.linkedin_reply_draft'), $this->user->fresh()->credits_balance);
    }

    public function test_drafting_a_reply_without_enough_credits_fails_gracefully_and_spends_nothing(): void
    {
        $this->user->update(['credits_balance' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('offers.linkedin.reply-assistant.store', $this->offer), [
                'their_message' => 'Hey, how much does this cost?',
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, LinkedinReplyDraft::count());
    }

    public function test_an_ai_failure_shows_an_error_and_spends_no_credits(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andThrow(new AIGenerationException('Provider unavailable'));
        });

        $response = $this->actingAs($this->user)
            ->post(route('offers.linkedin.reply-assistant.store', $this->offer), [
                'their_message' => 'Hey, how much does this cost?',
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(1000, $this->user->fresh()->credits_balance);
        $this->assertSame(0, LinkedinReplyDraft::count());
    }

    public function test_the_page_lists_recent_drafts_for_this_offer_only(): void
    {
        LinkedinReplyDraft::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'their_message' => 'A', 'draft_reply' => 'Reply A']);
        $otherOffer = Offer::create(['user_id' => $this->user->id, 'product_name' => 'B', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        LinkedinReplyDraft::create(['user_id' => $this->user->id, 'offer_id' => $otherOffer->id, 'their_message' => 'B', 'draft_reply' => 'Reply B']);

        $response = $this->actingAs($this->user)->get(route('offers.linkedin.reply-assistant', $this->offer));

        $response->assertOk()->assertSee('Reply A')->assertDontSee('Reply B');
    }

    public function test_a_user_cannot_use_the_reply_assistant_on_another_users_offer(): void
    {
        $other = User::factory()->create();
        $other->assignRole('user');

        $this->actingAs($other)
            ->get(route('offers.linkedin.reply-assistant', $this->offer))
            ->assertForbidden();
    }
}
