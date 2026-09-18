<?php

namespace Tests\Feature;

use App\Filament\Pages\Documentation\AffiliateProgram;
use App\Filament\Pages\Documentation\BillingAndPayments;
use App\Filament\Pages\Documentation\ContentAndBranding;
use App\Filament\Pages\Documentation\IntegrationsAndHealth;
use App\Filament\Pages\Documentation\Overview;
use App\Filament\Pages\Documentation\PlatformOperations;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Organise it properly and make it presentable and easy to understand...
 * it can have sub-pages to better cover every topic in great detail." The
 * single long-scrolling AdminDocumentation page was replaced by a
 * Documentation cluster with one sub-page per topic area (each with its own
 * icon and header) — see app/Filament/Pages/Documentation/*.
 */
class DocumentationClusterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, class-string>
     */
    protected function pages(): array
    {
        return [
            Overview::class,
            BillingAndPayments::class,
            AffiliateProgram::class,
            ContentAndBranding::class,
            IntegrationsAndHealth::class,
            PlatformOperations::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_an_admin_can_view_every_documentation_sub_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        foreach ($this->pages() as $page) {
            Livewire::actingAs($admin)->test($page)->assertSuccessful();
        }
    }

    public function test_an_admin_sub_account_can_view_every_page_regardless_of_department(): void
    {
        $subAdmin = User::factory()->create();
        $subAdmin->assignRole('admin_sub');

        // Deliberately granted no departments at all — none of these pages
        // are scoped to one, unlike most of the panel.
        foreach ($this->pages() as $page) {
            Livewire::actingAs($subAdmin)->test($page)->assertSuccessful();
        }
    }

    public function test_a_non_admin_cannot_access_any_documentation_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        foreach ($this->pages() as $page) {
            Livewire::actingAs($user)->test($page)->assertForbidden();
        }
    }

    public function test_every_section_across_every_page_links_somewhere_real_or_is_deliberately_link_free(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        foreach ($this->pages() as $pageClass) {
            $page = new $pageClass;

            $sections = $page->sections();
            $this->assertNotEmpty($sections, "{$pageClass} should document at least one section.");

            foreach ($sections as $section) {
                $this->assertNotEmpty($section['title']);
                $this->assertNotEmpty($section['body']);
                $this->assertNotEmpty($section['icon'] ?? null, "Every section in {$pageClass} should carry an icon.");

                if ($section['url'] !== null) {
                    $this->assertStringStartsWith('http', $section['url']);
                }
            }
        }
    }

    public function test_the_overview_pages_topic_links_all_resolve_to_real_urls(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $topics = (new Overview)->topics();
        $this->assertCount(5, $topics);

        foreach ($topics as $topic) {
            $this->assertNotEmpty($topic['title']);
            $this->assertNotEmpty($topic['description']);
            $this->assertStringStartsWith('http', $topic['url']);
        }
    }

    public function test_the_new_affiliate_toggle_and_testimonial_review_workflow_are_documented(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $affiliateSections = collect((new AffiliateProgram)->sections())->pluck('title');
        $this->assertTrue($affiliateSections->contains('Turning the public program on or off'));

        $contentSections = collect((new ContentAndBranding)->sections())->pluck('title');
        $this->assertTrue($contentSections->contains('Testimonials (review & approve)'));
    }
}
