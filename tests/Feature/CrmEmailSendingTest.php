<?php

namespace Tests\Feature;

use App\Mail\CrmNurtureEmail;
use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CRM/email dashboard: actually sending an AI-drafted nurture step to a
 * real contact (CrmEmailService), plus the public tracking/unsubscribe
 * routes that back it.
 */
class CrmEmailSendingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Offer $offer;

    protected CrmContact $contact;

    protected Generation $generation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        $this->offer = Offer::create([
            'user_id' => $this->user->id, 'product_name' => 'Acme Widget',
            'product_url' => 'https://acme.example/widget', 'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);

        $this->contact = CrmContact::create([
            'user_id' => $this->user->id, 'name' => 'Lee Prospect', 'company' => 'Prospect Co',
            'email' => 'lee@example.com', 'source' => 'manual', 'status' => 'contacted',
        ]);

        $this->generation = Generation::create([
            'user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'module' => 'email_nurture',
            'input' => ['offer_id' => $this->offer->id, 'contact_id' => $this->contact->id],
            'status' => 'completed',
            'output_meta' => [
                'emails' => [
                    ['step' => 1, 'send_timing' => 'Day 0', 'subject' => 'Quick hello', 'body' => 'Hi Lee, wanted to introduce myself.'],
                    ['step' => 4, 'send_timing' => 'Day 7', 'subject' => 'The tool I mentioned', 'body' => 'Here it is: {{AFFILIATE_LINK}} — worth a look.'],
                ],
            ],
        ]);
    }

    public function test_sending_a_step_emails_the_contact_and_records_it(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->user)
            ->post(route('generations.nurture.send', $this->generation), ['step' => 1]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(CrmNurtureEmail::class, function (CrmNurtureEmail $mail) {
            return $mail->hasTo('lee@example.com') && $mail->send->subject === 'Quick hello';
        });

        $send = CrmEmailSend::firstOrFail();
        $this->assertSame('sent', $send->status);
        $this->assertSame(1, $send->sequence_step);
        $this->assertSame($this->contact->id, $send->crm_contact_id);
        $this->assertSame($this->generation->id, $send->generation_id);
        $this->assertNotNull($send->sent_at);
        $this->assertStringContainsString('introduce myself', $send->body);
    }

    public function test_a_link_in_the_sent_body_is_rewritten_through_click_tracking(): void
    {
        Mail::fake();

        $this->actingAs($this->user)
            ->post(route('generations.nurture.send', $this->generation), ['step' => 4]);

        $send = CrmEmailSend::firstOrFail();

        $this->assertStringNotContainsString('{{AFFILIATE_LINK}}', $send->body);
        $this->assertStringContainsString(route('crm.track.click', $send->tracking_token), $send->body);
        $this->assertNotNull($send->link_destination);
        $this->assertStringContainsString('/go/', $send->link_destination);
    }

    public function test_sending_is_blocked_for_an_unsubscribed_contact(): void
    {
        Mail::fake();
        $this->contact->update(['unsubscribed_at' => now()]);

        $response = $this->actingAs($this->user)
            ->post(route('generations.nurture.send', $this->generation), ['step' => 1]);

        $response->assertSessionHas('error');
        Mail::assertNothingSent();
        $this->assertSame(0, CrmEmailSend::count());
    }

    public function test_sending_is_blocked_when_the_contact_has_no_email(): void
    {
        Mail::fake();
        $this->contact->update(['email' => null]);

        $this->actingAs($this->user)
            ->post(route('generations.nurture.send', $this->generation), ['step' => 1])
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_a_user_cannot_send_another_users_generation(): void
    {
        Mail::fake();
        $other = User::factory()->create();
        $other->assignRole('user');

        $this->actingAs($other)
            ->post(route('generations.nurture.send', $this->generation), ['step' => 1])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_the_open_pixel_records_an_open(): void
    {
        Mail::fake();
        $this->actingAs($this->user)->post(route('generations.nurture.send', $this->generation), ['step' => 1]);
        $send = CrmEmailSend::firstOrFail();

        $this->get(route('crm.track.open', $send->tracking_token))->assertOk();
        $this->get(route('crm.track.open', $send->tracking_token))->assertOk();

        $send->refresh();
        $this->assertNotNull($send->opened_at);
        $this->assertSame(2, $send->open_count);
    }

    public function test_the_click_link_redirects_to_the_stored_destination_and_records_a_click(): void
    {
        Mail::fake();
        $this->actingAs($this->user)->post(route('generations.nurture.send', $this->generation), ['step' => 4]);
        $send = CrmEmailSend::firstOrFail();

        $response = $this->get(route('crm.track.click', $send->tracking_token));

        $response->assertRedirect($send->link_destination);

        $send->refresh();
        $this->assertNotNull($send->first_clicked_at);
        $this->assertSame(1, $send->click_count);
    }

    public function test_unsubscribing_marks_the_contact_and_is_idempotent(): void
    {
        $token = $this->contact->unsubscribeToken();

        $this->get(route('crm.unsubscribe', $token))->assertOk();
        $this->assertNotNull($this->contact->fresh()->unsubscribed_at);

        $firstUnsubscribedAt = $this->contact->fresh()->unsubscribed_at;

        // Visiting again doesn't move the timestamp or error.
        $this->get(route('crm.unsubscribe', $token))->assertOk();
        $this->assertEquals($firstUnsubscribedAt, $this->contact->fresh()->unsubscribed_at);
    }
}
