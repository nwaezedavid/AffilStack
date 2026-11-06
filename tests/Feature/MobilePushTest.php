<?php

namespace Tests\Feature;

use App\Jobs\CheckMobilePushReceipts;
use App\Jobs\SendMobilePush;
use App\Models\CrmContact;
use App\Models\CrmReply;
use App\Models\MobileDevice;
use App\Models\MobileIntegrationSetting;
use App\Models\User;
use App\Notifications\AgentUpdate;
use App\Notifications\CrmReplyReceived;
use App\Notifications\GenerationCompleted;
use App\Services\Mobile\Push\ExpoPushClient;
use App\Services\Mobile\Push\MobilePush;
use App\Services\Mobile\Push\PushCategories;
use App\Services\Mobile\Push\PushMessage;
use App\Services\Mobile\Push\PushMessageFactory;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Phone notifications (phase 5): dashboard notifications reach the
 * customer's phones through Expo, respecting the admin switch, the
 * customer's choices and phones that went away.
 */
class MobilePushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        MobileIntegrationSetting::current()->update(['push_enabled' => true]);
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['notify_email_on_completion' => false]);
        $user->assignRole('user');

        return $user;
    }

    protected function generationFinished(User $user, bool $success = true): void
    {
        $user->notify(new GenerationCompleted('blog_article', 'Widget Pro', $success, 'https://app.example.com/offers/7/features/blog'));
    }

    protected function fakeExpo(array $tickets = [['status' => 'ok', 'id' => 'ticket-1']], array $receipts = []): void
    {
        Http::fake([
            ExpoPushClient::SEND_ENDPOINT => Http::response(['data' => $tickets]),
            ExpoPushClient::RECEIPTS_ENDPOINT => Http::response(['data' => $receipts]),
        ]);
    }

    public function test_a_finished_article_is_queued_for_the_customers_phones(): void
    {
        Queue::fake();
        $user = $this->customer();
        MobileDevice::factory()->for($user)->withPush()->create();

        $this->generationFinished($user);

        Queue::assertPushed(SendMobilePush::class, fn (SendMobilePush $job): bool => $job->userId === $user->id
            && $job->message['category'] === PushCategories::CONTENT
            && $job->message['title'] === 'Your blog article is ready'
            && $job->message['url'] === 'https://app.example.com/offers/7/features/blog');
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_nothing_is_queued_when_push_is_off_switched_off_or_there_is_no_phone(): void
    {
        Queue::fake();

        $noPhone = $this->customer();
        $this->generationFinished($noPhone);

        $signedOut = $this->customer();
        MobileDevice::factory()->for($signedOut)->withPush()->revoked()->create();
        $this->generationFinished($signedOut);

        $noPermission = $this->customer();
        MobileDevice::factory()->for($noPermission)->create();
        $this->generationFinished($noPermission);

        $optedOut = $this->customer(['push_preferences' => [PushCategories::CONTENT => false]]);
        MobileDevice::factory()->for($optedOut)->withPush()->create();
        $this->generationFinished($optedOut);

        Queue::assertNothingPushed();

        $user = $this->customer();
        MobileDevice::factory()->for($user)->withPush()->create();
        MobileIntegrationSetting::current()->update(['push_enabled' => false]);
        $this->generationFinished($user);

        Queue::assertNothingPushed();
    }

    public function test_switching_one_kind_off_keeps_the_others(): void
    {
        Queue::fake();
        $user = $this->customer(['push_preferences' => [PushCategories::CONTENT => false]]);
        MobileDevice::factory()->for($user)->withPush()->create();

        $this->generationFinished($user);
        $user->notify(new AgentUpdate('offer_scout', 'Offer Scout found a program', 'Descript pays 40% recurring.', 'https://app.example.com/agents'));

        Queue::assertPushed(SendMobilePush::class, 1);
        Queue::assertPushed(SendMobilePush::class, fn (SendMobilePush $job): bool => $job->message['category'] === PushCategories::AGENTS);
    }

    public function test_the_job_sends_one_message_per_phone_with_the_link_to_open(): void
    {
        Queue::fake();
        $this->fakeExpo([['status' => 'ok', 'id' => 'ticket-1'], ['status' => 'ok', 'id' => 'ticket-2']]);
        $user = $this->customer();
        $iphone = MobileDevice::factory()->for($user)->withPush('ExponentPushToken[iphone]')->create();
        $pixel = MobileDevice::factory()->for($user)->withPush('ExponentPushToken[pixel]')->create();
        MobileDevice::factory()->withPush('ExponentPushToken[someone-else]')->create();

        (new SendMobilePush($user->id, (new PushMessage(PushCategories::CONTENT, 'Your blog article is ready', 'For Widget Pro.', 'https://app.example.com/offers/7'))->toArray()))->handle(app(MobilePush::class));

        Http::assertSent(function (HttpRequest $request): bool {
            $messages = $request->data();

            return $request->url() === ExpoPushClient::SEND_ENDPOINT
                && collect($messages)->pluck('to')->sort()->values()->all() === ['ExponentPushToken[iphone]', 'ExponentPushToken[pixel]']
                && $messages[0]['title'] === 'Your blog article is ready'
                && $messages[0]['data'] === ['url' => 'https://app.example.com/offers/7', 'category' => PushCategories::CONTENT];
        });
        Queue::assertPushed(CheckMobilePushReceipts::class, fn (CheckMobilePushReceipts $job): bool => $job->ticketDevices == ['ticket-1' => $iphone->id, 'ticket-2' => $pixel->id] || $job->ticketDevices == ['ticket-1' => $pixel->id, 'ticket-2' => $iphone->id]);
    }

    public function test_a_phone_the_app_was_deleted_from_loses_its_push_token(): void
    {
        Queue::fake();
        $this->fakeExpo([['status' => 'error', 'message' => '"ExponentPushToken[gone]" is not a registered push notification recipient', 'details' => ['error' => 'DeviceNotRegistered']]]);
        $user = $this->customer();
        $device = MobileDevice::factory()->for($user)->withPush('ExponentPushToken[gone]')->create();

        (new SendMobilePush($user->id, (new PushMessage(PushCategories::AGENTS, 'Hi', 'There'))->toArray()))->handle(app(MobilePush::class));

        $this->assertNull($device->fresh()->push_token);
        $this->assertNull($device->fresh()->push_token_hash);
        Queue::assertNotPushed(CheckMobilePushReceipts::class);
    }

    public function test_receipts_forget_phones_apple_or_google_no_longer_accept(): void
    {
        $this->fakeExpo(receipts: [
            'ticket-gone' => ['status' => 'error', 'message' => 'The device cannot receive push notifications anymore', 'details' => ['error' => 'DeviceNotRegistered']],
            'ticket-fine' => ['status' => 'ok'],
        ]);
        $gone = MobileDevice::factory()->withPush()->create();
        $fine = MobileDevice::factory()->withPush()->create();

        app()->call([new CheckMobilePushReceipts(['ticket-gone' => $gone->id, 'ticket-fine' => $fine->id]), 'handle']);

        $this->assertNull($gone->fresh()->push_token);
        $this->assertNotNull($fine->fresh()->push_token);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === ExpoPushClient::RECEIPTS_ENDPOINT && $request['ids'] === ['ticket-gone', 'ticket-fine']);
    }

    public function test_the_expo_access_token_from_admin_is_used_when_set(): void
    {
        $this->fakeExpo();
        MobileIntegrationSetting::current()->update(['credentials' => ['expo_access_token' => 'expo-secret-123']]);

        app(ExpoPushClient::class)->send([(new PushMessage('test', 'Hi', 'There'))->forExpo('ExponentPushToken[x]')]);

        Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Authorization', 'Bearer expo-secret-123'));
        $this->assertStringNotContainsString('expo-secret-123', (string) MobileIntegrationSetting::current()->getRawOriginal('credentials'));
    }

    public function test_a_suspended_account_gets_nothing_even_if_already_queued(): void
    {
        $this->fakeExpo();
        $user = $this->customer(['is_suspended' => true]);
        MobileDevice::factory()->for($user)->withPush()->create();

        (new SendMobilePush($user->id, (new PushMessage(PushCategories::CONTENT, 'Hi', 'There'))->toArray()))->handle(app(MobilePush::class));

        Http::assertNothingSent();
    }

    public function test_a_phone_problem_never_breaks_the_notification(): void
    {
        $this->mock(PushMessageFactory::class, fn (MockInterface $mock) => $mock->shouldReceive('make')->andThrow(new RuntimeException('boom')));
        $user = $this->customer();
        MobileDevice::factory()->for($user)->withPush()->create();

        $this->generationFinished($user);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_buyer_replies_show_who_wrote_and_what_they_said(): void
    {
        $user = $this->customer();
        $contact = CrmContact::create(['user_id' => $user->id, 'name' => 'Chidi Okeke', 'email' => 'chidi@example.com', 'source' => 'manual', 'status' => 'contacted']);
        $reply = new CrmReply(['crm_contact_id' => $contact->id, 'from_email' => 'chidi@example.com', 'body_text' => 'Does it work for a team of 12?']);
        $reply->setRelation('contact', $contact);
        $notification = new CrmReplyReceived($reply);

        $message = app(PushMessageFactory::class)->make($notification, ['reason' => 'Chidi Okeke replied to your email', 'url' => 'https://app.example.com/crm/inbox/'.$contact->id]);

        $this->assertSame(PushCategories::INBOX, $message->category);
        $this->assertSame('Chidi Okeke replied to your email', $message->title);
        $this->assertSame('Does it work for a team of 12?', $message->body);
    }

    public function test_admin_and_marketing_notifications_never_reach_a_phone(): void
    {
        $marketing = new class extends Notification {};

        $this->assertNull(app(PushMessageFactory::class)->make($marketing, ['url' => 'https://example.com']));
    }

    public function test_a_failed_job_says_so_kindly(): void
    {
        Queue::fake();
        $user = $this->customer();
        MobileDevice::factory()->for($user)->withPush()->create();

        $this->generationFinished($user, success: false);

        Queue::assertPushed(SendMobilePush::class, fn (SendMobilePush $job): bool => $job->message['title'] === 'Your blog article couldn\'t be finished');
    }
}
