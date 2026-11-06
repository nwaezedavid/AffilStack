<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\MobileDevice;
use App\Models\MobileIntegrationSetting;
use App\Models\User;
use App\Services\Mobile\Push\ExpoPushClient;
use App\Services\Mobile\Push\PushCategories;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * More > Notifications in the phone app: per-kind switches and a test
 * notification to the phone in your hand.
 */
class MobileNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected MobileDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        $this->device = MobileDevice::factory()->for($this->user)->create();
    }

    /**
     * @return array<string, string>
     */
    protected function phone(): array
    {
        $plain = 'afmt_'.Str::random(48);

        ApiToken::create([
            'user_id' => $this->user->id,
            'mobile_device_id' => $this->device->id,
            'type' => 'mobile',
            'scope' => 'full',
            'name' => 'AffilStack app',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
        ]);

        return ['Authorization' => 'Bearer '.$plain];
    }

    public function test_every_kind_starts_switched_on(): void
    {
        $this->getJson('/api/v1/mobile/notifications/preferences', $this->phone())
            ->assertOk()
            ->assertJsonPath('data.push_available', false)
            ->assertJsonPath('data.this_device_registered', false)
            ->assertJsonCount(count(PushCategories::ALL), 'data.categories')
            ->assertJsonPath('data.categories.0.key', PushCategories::CONTENT)
            ->assertJsonPath('data.categories.0.enabled', true);
    }

    public function test_a_kind_can_be_switched_off_and_back_on(): void
    {
        $headers = $this->phone();

        $this->putJson('/api/v1/mobile/notifications/preferences', ['categories' => [PushCategories::WEEKLY => false, PushCategories::INBOX => true]], $headers)
            ->assertOk()
            ->assertJsonPath('data.categories.3.key', PushCategories::WEEKLY)
            ->assertJsonPath('data.categories.3.enabled', false);

        $this->assertFalse(PushCategories::allows($this->user->fresh(), PushCategories::WEEKLY));
        $this->assertTrue(PushCategories::allows($this->user->fresh(), PushCategories::CONTENT));

        $this->putJson('/api/v1/mobile/notifications/preferences', ['categories' => [PushCategories::WEEKLY => true]], $headers)->assertOk();
        $this->assertTrue(PushCategories::allows($this->user->fresh(), PushCategories::WEEKLY));
    }

    public function test_unknown_kinds_are_refused(): void
    {
        $this->putJson('/api/v1/mobile/notifications/preferences', ['categories' => ['marketing' => true]], $this->phone())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categories');
    }

    public function test_the_test_notification_explains_why_it_cannot_be_sent(): void
    {
        $headers = $this->phone();

        $this->postJson('/api/v1/mobile/notifications/test', [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Phone notifications are switched off for AffilStack right now.');

        MobileIntegrationSetting::current()->update(['push_enabled' => true]);

        $this->postJson('/api/v1/mobile/notifications/test', [], $headers)
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'This phone hasn\'t allowed AffilStack notifications yet. Turn them on in the phone\'s settings, then open the app again.']);
    }

    public function test_the_test_notification_goes_to_this_phone_only(): void
    {
        Http::fake([ExpoPushClient::SEND_ENDPOINT => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]]), ExpoPushClient::RECEIPTS_ENDPOINT => Http::response(['data' => []])]);
        MobileIntegrationSetting::current()->update(['push_enabled' => true]);
        $headers = $this->phone();
        MobileDevice::factory()->for($this->user)->withPush('ExponentPushToken[other-phone]')->create();

        $this->putJson('/api/v1/mobile/devices/current', ['push_provider' => 'expo', 'push_token' => 'ExponentPushToken[this-phone]'], $headers)->assertOk();
        $this->getJson('/api/v1/mobile/notifications/preferences', $headers)->assertJsonPath('data.this_device_registered', true);

        $this->postJson('/api/v1/mobile/notifications/test', [], $headers)->assertOk();

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === ExpoPushClient::SEND_ENDPOINT
            && count($request->data()) === 1
            && $request->data()[0]['to'] === 'ExponentPushToken[this-phone]');
    }

    public function test_developer_tokens_cannot_change_notification_settings(): void
    {
        $plain = ApiToken::generate($this->user, 'Script')['plainText'];

        $this->getJson('/api/v1/mobile/notifications/preferences', ['Authorization' => "Bearer {$plain}"])->assertForbidden();
    }
}
