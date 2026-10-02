<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * The redesigned Browser Extension dashboard page and the v1.2 package
 * itself (agent 09).
 */
class BrowserExtensionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_explains_the_workflow_and_shows_not_connected_until_a_token_is_used(): void
    {
        $user = User::factory()->create();
        ApiToken::generate($user, 'Work laptop');

        $this->actingAs($user)->get(route('extension.index'))
            ->assertOk()
            ->assertSee('Google Maps → CRM → Campaign')
            ->assertSee('LinkedIn → message → AI replies')
            ->assertSee('Not connected yet')
            ->assertSee('Waiting for first use')
            ->assertDontSee('Extension connected');
    }

    public function test_a_used_token_shows_the_extension_as_connected(): void
    {
        $user = User::factory()->create();
        $token = ApiToken::generate($user, 'Work laptop')['token'];
        $token->update(['last_used_at' => now()->subMinutes(5)]);

        $this->actingAs($user)->get(route('extension.index'))
            ->assertOk()
            ->assertSee('Extension connected')
            ->assertSee('Last used 5 minutes ago · Work laptop');
    }

    public function test_the_page_lists_research_keywords_with_maps_and_linkedin_search_links(): void
    {
        $user = User::factory()->create(['city' => 'Lagos', 'country' => 'Nigeria']);
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'Acme POS', 'product_url' => 'https://acme.example',
            'affiliate_network' => 'Impact', 'status' => 'ready',
            'research_data' => [
                'google_maps_keywords' => [['keyword' => 'restaurants', 'why' => 'They take payments all day']],
                'suggested_locations' => ['Ikeja, Lagos'],
                'linkedin_keywords' => ['people' => ['restaurant owner'], 'companies' => ['restaurant group']],
            ],
        ]);
        $list = CrmList::forOffer($offer);
        $contact = CrmContact::create(['user_id' => $user->id, 'name' => 'Mama Put', 'source' => 'google_maps', 'status' => 'new', 'raw_data' => ['captured_via' => 'chrome_extension']]);
        $list->addContact($contact);

        $this->actingAs($user)->get(route('extension.index'))
            ->assertOk()
            ->assertSee('https://www.google.com/maps/search/restaurants+in+Ikeja%2C+Lagos', false)
            ->assertSee('https://www.linkedin.com/search/results/people/?keywords=restaurant%20owner', false)
            ->assertSee(route('crm.lists.show', $list), false)
            ->assertSee(route('crm.campaigns.create', ['list' => $list->id]), false);
    }

    public function test_without_researched_products_the_page_prompts_research(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('extension.index'))
            ->assertOk()
            ->assertSee('Research a product to get your keywords');
    }

    public function test_the_package_is_v12_with_store_friendly_permissions_only(): void
    {
        $manifest = json_decode((string) file_get_contents(resource_path('browser-extension/manifest.json')), true);

        $this->assertSame('1.2.0', $manifest['version']);
        $this->assertSame(config('extension.version'), $manifest['version']);
        $this->assertSame(['activeTab', 'scripting', 'storage'], $manifest['permissions']);
        $this->assertArrayNotHasKey('host_permissions', $manifest);
        $this->assertArrayNotHasKey('content_scripts', $manifest);
        $this->assertArrayNotHasKey('background', $manifest);
        $this->assertLessThanOrEqual(132, mb_strlen($manifest['description']));
        $this->assertLessThanOrEqual(45, mb_strlen($manifest['name']));
    }

    public function test_the_download_includes_the_v12_files_but_not_the_store_listing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('extension.download'));

        $response->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        foreach (['manifest.json', 'config.js', 'api.js', 'extractor.js', 'popup.html', 'popup.css', 'popup.js', 'options.html', 'options.js', 'README.md'] as $file) {
            $this->assertContains('affilstack-extension/'.$file, $names);
        }
        $this->assertEmpty(array_filter($names, fn (string $name) => str_contains($name, '/store/')));
    }
}
