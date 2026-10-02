<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookDelivery;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\Offer;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\ApiDocs\CodeSampleGenerator;
use App\Services\ApiDocs\OpenApiReference;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The relaunched API & Integrations hub (Dashboard\ApiAccessController):
 * tabbed sections, an endpoint reference generated from the OpenAPI spec,
 * per-endpoint code samples, webhook test events and SDK downloads.
 */
class ApiIntegrationsHubTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_every_section_renders_with_the_users_own_base_url(): void
    {
        $baseUrl = url('/api/v1');

        $response = $this->actingAs($this->user)->get(route('api-access.index'))->assertOk();

        foreach (['overview', 'quick-start', 'no-code', 'code', 'reference', 'webhooks', 'usage', 'errors'] as $tab) {
            $response->assertSee('data-tab-panel="'.$tab.'"', false);
        }

        $response->assertSee('What is the AffilStack API?')
            ->assertSee('Make your first request')
            ->assertSee('curl &quot;'.$baseUrl.'/me&quot;', false)
            ->assertSee($baseUrl.'/crm-contacts')
            ->assertSee('Webhooks by Zapier')
            ->assertSee('HTTP → Make a request')
            ->assertSee('Header Auth')
            ->assertSee('UrlFetchApp.fetch')
            ->assertSee('API by Pabbly')
            ->assertSee('Event catalogue')
            ->assertSee('Rate limits by plan')
            ->assertSee('Idempotency keys')
            ->assertSee('Never expose a token in front-end code');
    }

    public function test_the_endpoint_reference_lists_every_path_and_method_in_the_spec(): void
    {
        $spec = Yaml::parseFile(resource_path('openapi/openapi.yaml'));
        $response = $this->actingAs($this->user)->get(route('api-access.index', ['tab' => 'reference']))->assertOk();
        $endpoints = collect(app(OpenApiReference::class)->endpoints());

        $operations = 0;

        foreach ($spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $operations++;
                $endpoint = $endpoints->first(fn ($e) => $e['spec_path'] === $path && $e['method'] === strtoupper($method));

                $this->assertNotNull($endpoint, "No reference entry for {$method} {$path}");
                $response->assertSee('id="'.$endpoint['anchor'].'"', false);
                $response->assertSee(substr($path, 3));
                $response->assertSee($methods[$method]['summary']);
            }
        }

        $this->assertSame($operations, $endpoints->count());
    }

    public function test_the_reference_shows_scope_cost_and_idempotency_from_routes_and_config(): void
    {
        $createOffer = collect(app(OpenApiReference::class)->endpoints())->firstWhere('id', 'createOffer');

        $this->assertSame('write', $createOffer['scope']);
        $this->assertSame(75, $createOffer['cost_cents']);
        $this->assertTrue($createOffer['idempotent']);
        $this->assertSame('api.v1.offers.store', $createOffer['route_name']);
        $this->assertSame('Acme Widget', $createOffer['body_example']['product_name']);

        $this->actingAs($this->user)->get(route('api-access.index'))
            ->assertSee('$0.75 per call from the API wallet')
            ->assertSee('Yes — send an Idempotency-Key header')
            ->assertSee('data-try-form', false);
    }

    public function test_code_samples_cover_every_language_and_use_the_sdk_methods(): void
    {
        $endpoint = collect(app(OpenApiReference::class)->endpoints())->firstWhere('id', 'listGenerations');
        $samples = (new CodeSampleGenerator('https://app.test/api/v1'))->forEndpoint($endpoint);

        $this->assertSame(array_keys(CodeSampleGenerator::LANGUAGES), array_keys($samples));
        $this->assertStringContainsString('curl -X GET "https://app.test/api/v1/generations?per_page=25&offer_id=42&module=blog_article"', $samples['curl']);
        $this->assertStringContainsString('client.listGenerations({ perPage: 25, offerId: 42, module: "blog_article" })', $samples['node_sdk']);
        $this->assertStringContainsString('client.list_generations(per_page=25, offer_id=42, module="blog_article")', $samples['python_sdk']);
        $this->assertStringContainsString('requests.get(', $samples['python']);
        $this->assertStringContainsString("curl_init('https://app.test/api/v1/generations?", $samples['php']);
    }

    public function test_creating_a_token_reveals_it_once_on_the_quick_start_tab(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('api-access.tokens.store'), ['name' => 'Make — lead sync', 'scope' => 'read_only', 'is_sandbox' => '1']);

        $response->assertRedirect(route('api-access.index', ['tab' => 'quick-start']));
        $plain = session('new_token');
        $this->assertStringStartsWith('aff_', $plain);

        $token = ApiToken::findByPlainText($plain);
        $this->assertTrue($token->isReadOnly());
        $this->assertTrue($token->is_sandbox);

        $this->actingAs($this->user)->get(route('api-access.index', ['tab' => 'quick-start']))
            ->assertSee($plain)
            ->assertSee('Read only')
            ->assertSee('Sandbox');

        // A plain reload no longer shows it.
        $this->actingAs($this->user)->get(route('api-access.index'))->assertDontSee($plain);
    }

    public function test_the_requested_tab_is_the_one_shown(): void
    {
        $html = $this->actingAs($this->user)->get(route('api-access.index', ['tab' => 'webhooks']))->getContent();

        $this->assertMatchesRegularExpression('/id="tab-webhooks" data-tab-panel="webhooks" class="space-y-6"/', $html);
        $this->assertMatchesRegularExpression('/id="tab-overview" data-tab-panel="overview" class="space-y-6 hidden"/', $html);
    }

    public function test_an_owner_can_send_a_signed_test_event_to_their_endpoint(): void
    {
        Queue::fake();
        $endpoint = $this->user->webhookEndpoints()->create([
            'url' => 'https://hooks.zapier.com/hooks/catch/1/abc/',
            'events' => ['crm_contact.created', 'earning.recorded'],
            'secret' => WebhookEndpoint::generateSecret(),
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('api-access.webhooks.test', $endpoint), ['event' => 'earning.recorded'])
            ->assertRedirect(route('api-access.index', ['tab' => 'webhooks']))
            ->assertSessionHas('success');

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->firstOrFail();
        $this->assertSame('earning.recorded', $delivery->event);
        $this->assertTrue($delivery->payload['test']);
        $this->assertSame(4900, $delivery->payload['amount_cents']);
        Queue::assertPushed(SendWebhookDelivery::class);

        $this->actingAs($this->user)
            ->post(route('api-access.webhooks.test', $endpoint), ['event' => 'referral.converted'])
            ->assertSessionHasErrors('event');
    }

    public function test_a_user_cannot_send_test_events_to_someone_elses_endpoint(): void
    {
        $other = User::factory()->create();
        $endpoint = $other->webhookEndpoints()->create([
            'url' => 'https://example.com/hook', 'events' => ['crm_contact.created'],
            'secret' => WebhookEndpoint::generateSecret(), 'is_active' => true,
        ]);

        $this->actingAs($this->user)->post(route('api-access.webhooks.test', $endpoint))->assertForbidden();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_the_delivery_log_shows_retry_status(): void
    {
        $endpoint = $this->user->webhookEndpoints()->create([
            'url' => 'https://example.com/hook', 'events' => ['crm_contact.created'],
            'secret' => WebhookEndpoint::generateSecret(), 'is_active' => true,
        ]);
        WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'crm_contact.created', 'payload' => [], 'status' => 'pending', 'attempts' => 2, 'response_status' => 503]);
        WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'crm_contact.created', 'payload' => [], 'status' => 'delivered', 'attempts' => 1, 'response_status' => 200]);

        $this->actingAs($this->user)->get(route('api-access.index', ['tab' => 'webhooks']))
            ->assertSee('Delivery log')
            ->assertSee('Retrying')
            ->assertSee('2 / '.config('webhooks.max_attempts'))
            ->assertSee('503')
            ->assertSee('Delivered')
            ->assertSee('Send test event');
    }

    public function test_the_usage_tab_lists_recent_requests_with_status_codes(): void
    {
        $token = ApiToken::generate($this->user, 'Reporting')['token'];
        ApiRequestLog::create(['api_token_id' => $token->id, 'user_id' => $this->user->id, 'route' => 'api.v1.crm-contacts.store', 'method' => 'POST', 'status_code' => 422, 'cost_cents' => 0]);
        ApiRequestLog::create(['api_token_id' => $token->id, 'user_id' => $this->user->id, 'route' => 'api.v1.offers.store', 'method' => 'POST', 'status_code' => 201, 'cost_cents' => 75]);

        $this->actingAs($this->user)->get(route('api-access.index', ['tab' => 'usage']))
            ->assertSee('Recent requests')
            ->assertSee('POST /crm-contacts')
            ->assertSee('422')
            ->assertSee('POST /offers')
            ->assertSee('Reporting')
            ->assertSee('Your plan', false);
    }

    public function test_the_sdks_can_be_downloaded(): void
    {
        $this->actingAs($this->user)->get(route('api-access.sdk', 'js'))
            ->assertOk()
            ->assertDownload('affilstack.js');

        $this->actingAs($this->user)->get(route('api-access.sdk', 'python'))
            ->assertOk()
            ->assertDownload('affilstack.py');

        $this->actingAs($this->user)->get('/api-access/sdk/ruby')->assertNotFound();
    }

    public function test_a_seat_sees_the_docs_but_not_owner_only_webhook_or_wallet_controls(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)->get(route('api-access.index'))
            ->assertOk()
            ->assertSee('Endpoint reference')
            ->assertSee('Webhook endpoints are managed by your account owner')
            ->assertDontSee('action="'.route('api-access.webhooks.store').'"', false)
            ->assertDontSee('Auto-recharge');

        $this->actingAs($seat)->get(route('api-access.sdk', 'js'))->assertOk();
    }
}
