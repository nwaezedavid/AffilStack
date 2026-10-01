<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleOAuthSettings;
use App\Models\CrmCampaign;
use App\Models\CrmCampaignRecipient;
use App\Models\CrmEmailSend;
use App\Models\CrmReply;
use App\Models\EmailConnection;
use App\Models\GoogleOauthSetting;
use App\Models\User;
use App\Notifications\CrmReplyReceived;
use App\Services\Crm\Imap\ImapTransport;
use App\Services\Crm\Imap\InboundEmail;
use App\Services\Crm\Inbox\GmailInboxReader;
use App\Services\Crm\Inbox\QuotedTextStripper;
use App\Services\Crm\Inbox\ReplyIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\CrmTestHelpers;
use Tests\Support\ScriptedImapTransport;
use Tests\TestCase;

/**
 * Reply detection: matching a mailbox message to our send (Message-ID,
 * Gmail thread, or sender), marking the recipient replied (which stops
 * follow-ups), the IMAP poller end to end against a scripted server, and
 * the Gmail API reader — inert until the admin toggle is on.
 */
class CrmReplyDetectionTest extends TestCase
{
    use CrmTestHelpers, RefreshDatabase;

    protected User $user;

    protected CrmEmailSend $send;

    protected CrmCampaignRecipient $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->user = $this->crmUser();
        $list = $this->listWithContacts($this->user, $this->offerFor($this->user), 1);
        $contact = $list->contacts()->first();
        $campaign = CrmCampaign::factory()->launched()->create(['user_id' => $this->user->id, 'crm_list_id' => $list->id, 'follow_up_steps' => [['delay_days' => 3, 'subject' => '', 'body' => 'Bump']]]);
        $this->recipient = CrmCampaignRecipient::create(['crm_campaign_id' => $campaign->id, 'crm_contact_id' => $contact->id, 'user_id' => $this->user->id, 'status' => 'sent', 'steps_sent' => 1, 'next_step_due_at' => now()->addDays(3)]);
        $this->send = CrmEmailSend::create([
            'user_id' => $this->user->id, 'crm_contact_id' => $contact->id, 'kind' => 'campaign', 'crm_campaign_id' => $campaign->id,
            'crm_campaign_recipient_id' => $this->recipient->id, 'subject' => 'Quick idea', 'body' => 'Hi', 'status' => 'sent',
            'sent_at' => now()->subDay(), 'tracking_token' => 'tok-1', 'message_id' => '<camp-1@mybiz.example>', 'provider_thread_id' => 'thread-9',
        ]);
    }

    protected function inbound(array $overrides = []): InboundEmail
    {
        return new InboundEmail(...$overrides + [
            'externalId' => '77', 'messageId' => '<reply-1@sunrise.example>', 'inReplyTo' => '<camp-1@mybiz.example>',
            'references' => ['<camp-1@mybiz.example>'], 'fromEmail' => $this->send->contact->email, 'fromName' => 'Ada Obi',
            'subject' => 'Re: Quick idea', 'date' => now(), 'textBody' => "Yes please, tell me more.\n\nOn Tue, Sep 29, 2026 at 9:00 AM Me <me@mybiz.example> wrote:\n> Hi",
        ]);
    }

    public function test_a_reply_is_matched_by_in_reply_to_and_stops_follow_ups(): void
    {
        $reply = app(ReplyIngestor::class)->ingest($this->user, $this->inbound(), 'imap');

        $this->assertNotNull($reply);
        $this->assertSame('message_id', $reply->matched_by);
        $this->assertSame('Yes please, tell me more.', $reply->body_clean);
        $this->assertSame($this->send->crm_campaign_id, $reply->crm_campaign_id);

        $this->recipient->refresh();
        $this->assertSame('replied', $this->recipient->status);
        $this->assertNull($this->recipient->next_step_due_at);
        $this->assertNotNull($this->send->contact->fresh()->replied_at);
        Notification::assertSentTo($this->user, CrmReplyReceived::class);
    }

    public function test_the_same_message_is_only_stored_once(): void
    {
        $ingestor = app(ReplyIngestor::class);
        $ingestor->ingest($this->user, $this->inbound(), 'imap');
        $this->assertNull($ingestor->ingest($this->user, $this->inbound(), 'imap'));

        $this->assertSame(1, CrmReply::count());
    }

    public function test_a_reply_without_threading_headers_falls_back_to_the_sender_address(): void
    {
        $reply = app(ReplyIngestor::class)->ingest($this->user, $this->inbound(['inReplyTo' => null, 'references' => [], 'fromEmail' => strtoupper($this->send->contact->email)]), 'imap');

        $this->assertSame('sender', $reply->matched_by);
    }

    public function test_a_gmail_thread_id_matches_too(): void
    {
        $reply = app(ReplyIngestor::class)->ingest($this->user, $this->inbound(['inReplyTo' => null, 'references' => [], 'fromEmail' => 'alias@other.example', 'threadId' => 'thread-9']), 'gmail');

        $this->assertSame('thread', $reply->matched_by);
    }

    public function test_unrelated_mail_and_old_conversations_are_ignored(): void
    {
        $ingestor = app(ReplyIngestor::class);

        $this->assertNull($ingestor->ingest($this->user, $this->inbound(['inReplyTo' => null, 'references' => [], 'fromEmail' => 'stranger@else.example']), 'imap'));
        $this->assertNull($ingestor->ingest($this->user, $this->inbound(['inReplyTo' => null, 'references' => [], 'date' => now()->addDays(60)]), 'imap'));
        $this->assertNull($ingestor->ingest($this->user, $this->inbound(['fromEmail' => 'me@mybiz.example', 'inReplyTo' => null, 'references' => []]), 'imap'));
        $this->assertSame(0, CrmReply::count());
    }

    public function test_quoted_history_and_signatures_are_stripped(): void
    {
        $this->assertSame('Sounds good!', QuotedTextStripper::strip("Sounds good!\n\nOn Mon, 28 Sep 2026 at 10:00, Ade <a@b.example>\nwrote:\n> old"));
        $this->assertSame('Call me.', QuotedTextStripper::strip("Call me.\n\nFrom: Me <me@x.example>\nSent: Monday\nTo: Ada\n\nOld text"));
        $this->assertSame("Thanks\nAda", QuotedTextStripper::strip("Thanks\nAda\n--\nAda Obi | CEO\n+234"));
        $this->assertSame('Oui merci', QuotedTextStripper::strip("Oui merci\n\nLe lun. 28 sept. 2026 à 10:00, Me <me@x.example> a écrit :\n> salut"));
    }

    public function test_the_imap_poller_reads_new_mail_and_files_replies(): void
    {
        $connection = $this->verifiedSmtp($this->user, [
            'imap_credentials' => ['host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'me@mybiz.example', 'password' => 'p@ss "word"'],
            'imap_status' => 'success', 'imap_uidvalidity' => 555, 'imap_last_uid' => 40,
        ]);

        $headers = "From: Ada Obi <{$this->send->contact->email}>\r\nSubject: Re: Quick idea\r\nDate: Wed, 30 Sep 2026 08:00:00 +0000\r\nMessage-ID: <reply-9@sunrise.example>\r\nIn-Reply-To: <camp-1@mybiz.example>\r\n\r\n";
        $spam = "From: promo@shop.example\r\nSubject: SALE\r\nMessage-ID: <promo@shop>\r\n\r\n";
        $body = "Interested! What's the price?\r\n";
        $transport = new ScriptedImapTransport([
            '/ LOGIN "me@mybiz.example" "p@ss \\\\"word\\\\""/' => "{TAG} OK Logged in\r\n",
            '/ SELECT /' => "* 50 EXISTS\r\n* OK [UIDVALIDITY 555] ok\r\n* OK [UIDNEXT 43] ok\r\n{TAG} OK done\r\n",
            '/UID SEARCH UID 41:\*/' => "* SEARCH 41 42\r\n{TAG} OK done\r\n",
            '/UID FETCH 41,42 \(UID/' => '* 1 FETCH (UID 41 BODYSTRUCTURE ("text" "plain" ("charset" "utf-8") NIL NIL "7bit" 30 1 NIL NIL NIL NIL) BODY[HEADER.FIELDS (FROM TO SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)] {'.strlen($headers)."}\r\n".$headers.")\r\n"
                .'* 2 FETCH (UID 42 BODYSTRUCTURE ("text" "plain" NIL NIL NIL "7bit" 5 1 NIL NIL NIL NIL) BODY[HEADER.FIELDS (FROM TO SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)] {'.strlen($spam)."}\r\n".$spam.")\r\n{TAG} OK done\r\n",
            '/UID FETCH 41 \(BODY/' => '* 1 FETCH (UID 41 BODY[1]<0> {'.strlen($body)."}\r\n".$body.")\r\n{TAG} OK done\r\n",
            '/UID FETCH 42 \(BODY/' => "* 2 FETCH (UID 42 BODY[1]<0> \"Buy\")\r\n{TAG} OK done\r\n",
            '/ LOGOUT/' => "* BYE\r\n{TAG} OK bye\r\n",
        ]);
        $this->app->instance(ImapTransport::class, $transport);

        $this->artisan('crm:poll-replies')->expectsOutputToContain('1 new repl')->assertSuccessful();

        $this->assertSame(['host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl', 'timeout' => 15], $transport->opened);
        $reply = CrmReply::sole();
        $this->assertSame("Interested! What's the price?", $reply->body_clean);
        $this->assertSame('imap', $reply->provider);
        $this->assertSame(42, (int) $connection->fresh()->imap_last_uid);
        $this->assertNotNull($connection->fresh()->imap_last_polled_at);
    }

    public function test_a_rejected_imap_login_during_polling_flags_the_mailbox(): void
    {
        $connection = $this->verifiedSmtp($this->user, [
            'imap_credentials' => ['host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'me@gmail.com', 'password' => 'normal-password'],
            'imap_status' => 'success',
        ]);
        $this->app->instance(ImapTransport::class, new ScriptedImapTransport([
            '/ LOGIN /' => "{TAG} NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)\r\n",
        ]));

        $this->artisan('crm:poll-replies')->assertSuccessful();

        $connection->refresh();
        $this->assertSame('failed', $connection->imap_status);
        $this->assertStringContainsString('App Password', $connection->imap_message);
    }

    public function test_gmail_api_reading_stays_off_until_the_admin_enables_it(): void
    {
        $connection = EmailConnection::create([
            'user_id' => $this->user->id, 'provider' => 'gmail', 'connected_email' => 'me@gmail.com', 'verification_status' => 'success',
            'gmail_read_granted' => true,
            'credentials' => ['access_token' => 'at', 'refresh_token' => 'rt', 'expires_at' => now()->addHour()->toIso8601String()],
        ]);
        GoogleOauthSetting::current()->update(['gmail_sending_enabled' => true, 'gmail_reading_enabled' => false, 'credentials' => ['client_id' => 'id', 'client_secret' => 's']]);
        Http::fake();

        $this->assertSame(['fetched' => 0, 'replies' => 0], app(GmailInboxReader::class)->poll($connection));
        $this->assertNull($connection->fresh()->replyDetectionMethod());
        Http::assertNothingSent();
    }

    public function test_gmail_api_reading_bootstraps_then_reads_incrementally_from_history(): void
    {
        $connection = EmailConnection::create([
            'user_id' => $this->user->id, 'provider' => 'gmail', 'connected_email' => 'me@gmail.com', 'verification_status' => 'success',
            'gmail_read_granted' => true,
            'credentials' => ['access_token' => 'at', 'refresh_token' => 'rt', 'expires_at' => now()->addHour()->toIso8601String()],
        ]);
        GoogleOauthSetting::current()->update(['gmail_sending_enabled' => true, 'gmail_reading_enabled' => true, 'credentials' => ['client_id' => 'id', 'client_secret' => 's']]);

        $message = fn (string $id, string $from, string $text) => [
            'id' => $id, 'threadId' => 'thread-9',
            'payload' => ['mimeType' => 'multipart/alternative', 'headers' => [
                ['name' => 'From', 'value' => $from], ['name' => 'Subject', 'value' => '=?UTF-8?Q?Re:_Quick_idea_=E2=9C=93?='],
                ['name' => 'Message-ID', 'value' => "<{$id}@mail.gmail.com>"], ['name' => 'In-Reply-To', 'value' => '<camp-1@mybiz.example>'],
                ['name' => 'Date', 'value' => 'Wed, 30 Sep 2026 08:00:00 +0000'],
            ], 'parts' => [
                ['mimeType' => 'text/plain', 'headers' => [['name' => 'Content-Type', 'value' => 'text/plain; charset=UTF-8']], 'body' => ['data' => rtrim(strtr(base64_encode($text), '+/', '-_'), '=')]],
                ['mimeType' => 'text/html', 'body' => ['data' => rtrim(strtr(base64_encode('<p>html</p>'), '+/', '-_'), '=')]],
            ]],
        ];

        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['historyId' => '1000']),
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'm1']]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m1*' => Http::response($message('m1', 'Ada <'.$this->send->contact->email.'>', 'Price please?')),
            'gmail.googleapis.com/gmail/v1/users/me/history*' => Http::response(['historyId' => '1010', 'history' => [['messagesAdded' => [['message' => ['id' => 'm2']]]]]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/m2*' => Http::response($message('m2', 'Ada <'.$this->send->contact->email.'>', 'Booked a call!')),
        ]);

        $this->assertSame('gmail', $connection->replyDetectionMethod());
        $this->assertSame(['fetched' => 1, 'replies' => 1], app(GmailInboxReader::class)->poll($connection));
        $this->assertSame('1000', $connection->fresh()->gmail_history_id);

        $this->assertSame(['fetched' => 1, 'replies' => 1], app(GmailInboxReader::class)->poll($connection->fresh()));
        $this->assertSame('1010', $connection->fresh()->gmail_history_id);

        $this->assertSame(['Price please?', 'Booked a call!'], CrmReply::orderBy('id')->pluck('body_clean')->all());
        $this->assertSame('Re: Quick idea ✓', CrmReply::first()->subject);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'startHistoryId=1000') && $request->hasHeader('Authorization', 'Bearer at'));
    }

    public function test_connect_gmail_asks_for_the_readonly_scope_only_when_enabled(): void
    {
        $settings = GoogleOauthSetting::current();
        $settings->update(['gmail_sending_enabled' => true, 'credentials' => ['client_id' => 'id.apps.googleusercontent.com', 'client_secret' => 's']]);

        $location = $this->actingAs($this->user)->get(route('email-connections.gmail.redirect'))->headers->get('Location');
        $this->assertStringNotContainsString('gmail.readonly', urldecode($location));

        $settings->update(['gmail_reading_enabled' => true]);
        $location = $this->actingAs($this->user)->get(route('email-connections.gmail.redirect'))->headers->get('Location');
        $this->assertStringContainsString('gmail.readonly', urldecode($location));

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'scope' => 'openid https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.readonly']),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'me@gmail.com']),
        ]);
        $this->actingAs($this->user)->get(route('email-connections.gmail.callback', ['state' => session('gmail_connect_state'), 'code' => 'c']));

        $this->assertTrue($this->user->fresh()->emailConnection->gmail_read_granted);
    }

    public function test_the_admin_page_saves_the_gmail_reading_toggle(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(GoogleOAuthSettings::class)
            ->assertSee('Allow Gmail inbox reading — requires Google CASA approval')
            ->fillForm([
                'gmail_sending_enabled' => true,
                'gmail_reading_enabled' => true,
                'client_id' => '123-abc.apps.googleusercontent.com',
                'client_secret' => 'secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = GoogleOauthSetting::current();
        $this->assertTrue($settings->gmail_reading_enabled);
        $this->assertTrue($settings->gmailReadingAvailable());

        $settings->update(['gmail_sending_enabled' => false]);
        $this->assertFalse($settings->fresh()->gmailReadingAvailable(), 'Reading needs the Gmail connection itself to be available');
    }

    public function test_unsubscribing_stops_pending_follow_ups(): void
    {
        $contact = $this->send->contact;

        $this->get(route('crm.unsubscribe', $contact->unsubscribeToken()))->assertOk();

        $this->assertSame('unsubscribed', $this->recipient->fresh()->status);
        $this->assertNull($this->recipient->fresh()->next_step_due_at);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
