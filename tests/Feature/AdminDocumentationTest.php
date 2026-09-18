<?php

namespace Tests\Feature;

use App\Filament\Pages\AdminDocumentation;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit item #4 — one admin-facing page explaining every settings screen in
 * the panel. Built last, after the wallet/Connections Health/refund policy
 * features it documents, so it covers the finished system.
 */
class AdminDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_an_admin_can_view_the_documentation_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(AdminDocumentation::class)
            ->assertSuccessful();
    }

    public function test_an_admin_sub_account_can_view_it_regardless_of_department(): void
    {
        $subAdmin = User::factory()->create();
        $subAdmin->assignRole('admin_sub');

        // Deliberately granted no departments at all — this page isn't
        // scoped to one, unlike most of the panel.
        Livewire::actingAs($subAdmin)
            ->test(AdminDocumentation::class)
            ->assertSuccessful();
    }

    public function test_a_non_admin_cannot_access_the_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test(AdminDocumentation::class)
            ->assertForbidden();
    }

    public function test_every_section_links_somewhere_real_or_is_deliberately_link_free(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $sections = (new AdminDocumentation)->sections();

        $this->assertNotEmpty($sections);

        foreach ($sections as $section) {
            $this->assertNotEmpty($section['title']);
            $this->assertNotEmpty($section['body']);

            if ($section['url'] !== null) {
                $this->assertStringStartsWith('http', $section['url']);
            }
        }

        $this->assertTrue(
            collect($sections)->firstWhere('title', 'Payment gateways')['url'] !== null
        );
        $this->assertTrue(
            collect($sections)->firstWhere('title', 'Connections Health (AI-assisted verification)')['url'] !== null
        );
    }
}
