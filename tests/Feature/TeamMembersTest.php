<?php

namespace Tests\Feature;

use App\Filament\Pages\AboutPageSettings;
use App\Filament\Resources\TeamMembers\Pages\CreateTeamMember;
use App\Filament\Resources\TeamMembers\Pages\ListTeamMembers;
use App\Filament\Resources\TeamMembers\TeamMemberResource;
use App\Models\SiteSetting;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "In the about section, I want you to include space for other team members
 * (the same way you built the founder section). I need to be able to
 * update details of my team (e.g. CEO, CFO, COO, etc.)."
 */
class TeamMembersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_the_team_section_is_hidden_until_someone_is_added(): void
    {
        $this->assertSame(0, TeamMember::count());

        $this->get('/about')->assertOk()->assertDontSee('id="team"', false)->assertDontSee('Meet the team');
    }

    public function test_visible_members_show_in_order_with_their_roles_and_links(): void
    {
        TeamMember::factory()->create(['name' => 'Fatima Bello', 'role' => 'Chief Financial Officer (CFO)', 'sort_order' => 2]);
        TeamMember::factory()->create(['name' => 'Chidi Okafor', 'role' => 'Chief Operating Officer (COO)', 'sort_order' => 1, 'linkedin_url' => 'https://www.linkedin.com/in/chidi', 'x_url' => 'javascript:alert(1)']);
        TeamMember::factory()->hidden()->create(['name' => 'Hidden Person']);

        $html = $this->get('/about')
            ->assertOk()
            ->assertSee('id="team"', false)
            ->assertSee('Meet the team')
            ->assertSee('Chief Financial Officer (CFO)')
            ->assertSee('https://www.linkedin.com/in/chidi', false)
            ->assertSee('Chidi Okafor on LinkedIn')
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('Hidden Person')
            // Team without a founder never shows the founder heading.
            ->assertDontSee('Behind')
            ->getContent();

        $this->assertLessThan(strpos($html, 'Fatima Bello'), strpos($html, 'Chidi Okafor'));
    }

    public function test_the_team_appears_after_the_founder(): void
    {
        SiteSetting::set('about_founder_name', 'Nwaeze David');
        TeamMember::factory()->create(['name' => 'Tunde Adeyemi', 'role' => 'CTO']);

        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Tunde Adeyemi'), strpos($html, 'Nwaeze David'));
    }

    public function test_the_cached_list_refreshes_when_a_member_changes(): void
    {
        $member = TeamMember::factory()->create(['name' => 'Old Name']);
        $this->get('/about')->assertSee('Old Name');

        $member->update(['name' => 'New Name']);
        $this->get('/about')->assertSee('New Name')->assertDontSee('Old Name');

        $member->delete();
        $this->get('/about')->assertDontSee('New Name');
    }

    public function test_admin_can_add_a_team_member(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateTeamMember::class)
            ->fillForm([
                'name' => 'Amaka Obi',
                'role' => 'Chief Executive Officer (CEO)',
                'bio' => 'Leads strategy.',
                'linkedin_url' => 'https://www.linkedin.com/in/amaka',
                'is_visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('team_members', ['name' => 'Amaka Obi', 'role' => 'Chief Executive Officer (CEO)']);
        $this->get('/about')->assertSee('Amaka Obi');
    }

    public function test_profile_links_must_be_web_addresses(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateTeamMember::class)
            ->fillForm(['name' => 'A', 'role' => 'CTO', 'website_url' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['website_url']);
    }

    public function test_admin_can_list_members_and_site_sub_accounts_can_manage_them(): void
    {
        $members = TeamMember::factory()->count(2)->create();

        Livewire::actingAs($this->admin)->test(ListTeamMembers::class)->assertCanSeeTableRecords($members);

        $site = User::factory()->create();
        $site->assignRole('admin_sub');
        $site->syncPermissions(['department.site']);
        $this->actingAs($site);
        $this->assertTrue(TeamMemberResource::canAccess());

        $support = User::factory()->create();
        $support->assignRole('admin_sub');
        $support->syncPermissions(['department.support']);
        $this->actingAs($support);
        $this->assertFalse(TeamMemberResource::canAccess());
    }

    public function test_the_about_page_settings_save_the_team_heading(): void
    {
        TeamMember::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(AboutPageSettings::class)
            ->fillForm(['about_team_heading' => 'The people behind the platform'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/about')->assertSee('The people behind the platform');
    }
}
