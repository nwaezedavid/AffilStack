<?php

namespace Tests\Feature;

use App\Filament\Resources\LifecycleEmails\LifecycleEmailResource;
use App\Filament\Resources\LifecycleEmails\Pages\EditLifecycleEmail;
use App\Filament\Resources\LifecycleEmails\Pages\ListLifecycleEmails;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\UserActivityOverview;
use App\Mail\LifecycleMail;
use App\Models\LifecycleEmail;
use App\Models\User;
use App\Support\Lifecycle\LifecycleSettings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLifecycleAndActivityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(['admin', 'super-admin']);
    }

    protected function customer(?int $daysSinceActive): User
    {
        $user = User::factory()->create();
        $user->forceFill(['last_active_at' => $daysSinceActive === null ? null : now()->subDays($daysSinceActive)])->save();

        return $user;
    }

    public function test_users_list_tabs_split_active_idle_inactive_and_never(): void
    {
        $active = $this->customer(2);
        $idle = $this->customer(12);
        $inactive = $this->customer(50);
        $never = $this->customer(null);

        $page = Livewire::actingAs($this->admin)->test(ListUsers::class);

        $page->set('activeTab', 'active')->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$idle, $inactive, $never]);
        $page->set('activeTab', 'idle')->assertCanSeeTableRecords([$idle])->assertCanNotSeeTableRecords([$active, $inactive, $never]);
        $page->set('activeTab', 'inactive')->assertCanSeeTableRecords([$inactive])->assertCanNotSeeTableRecords([$active, $idle, $never]);
        $page->set('activeTab', 'never')->assertCanSeeTableRecords([$never])->assertCanNotSeeTableRecords([$active, $idle, $inactive]);
    }

    public function test_users_list_activity_filter_and_badges(): void
    {
        $active = $this->customer(1);
        $inactive = $this->customer(90);

        Livewire::actingAs($this->admin)->test(ListUsers::class)
            ->assertTableColumnStateSet('activity_status', 'active', $active)
            ->assertTableColumnStateSet('activity_status', 'inactive', $inactive)
            ->filterTable('activity', 'inactive')
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_thresholds_from_settings_drive_the_tabs(): void
    {
        $user = $this->customer(10);
        LifecycleSettings::save(['active_within_days' => 14, 'inactive_after_days' => 45]);

        Livewire::actingAs($this->admin)->test(ListUsers::class)
            ->set('activeTab', 'active')
            ->assertCanSeeTableRecords([$user]);
    }

    public function test_the_activity_widget_renders_for_users_access_admins_only(): void
    {
        $this->customer(1);

        Livewire::actingAs($this->admin)->test(UserActivityOverview::class)
            ->assertSee('Active users')
            ->assertSee('Never logged in');

        $support = User::factory()->create();
        $support->assignRole('admin_sub');
        $this->actingAs($support);
        $this->assertFalse(UserActivityOverview::canView());
    }

    public function test_lifecycle_emails_list_shows_the_presets(): void
    {
        $page = Livewire::actingAs($this->admin)->test(ListLifecycleEmails::class)
            ->assertOk()
            ->assertCanSeeTableRecords(LifecycleEmail::where('key', 'birthday')->get());

        $page->set('activeTab', 'reengagement')
            ->assertCanSeeTableRecords(LifecycleEmail::where('type', 'reengagement')->get())
            ->assertCanNotSeeTableRecords(LifecycleEmail::where('key', 'birthday')->get());

        $page->set('activeTab', 'festive')
            ->searchTable('Christmas')
            ->assertCanSeeTableRecords(LifecycleEmail::where('key', 'festive.christmas')->get());
    }

    public function test_the_editor_shows_a_live_preview_and_saves_edits(): void
    {
        $email = LifecycleEmail::where('key', 'festive.christmas')->first();

        Livewire::actingAs($this->admin)->test(EditLifecycleEmail::class, ['record' => $email->getRouteKey()])
            ->assertOk()
            ->assertSee('Live preview')
            ->assertSee('Merry Christmas')
            ->fillForm(['subject' => 'Season’s greetings, {first_name}!', 'days_before' => 1])
            ->call('save')
            ->assertHasNoFormErrors();

        $email->refresh();
        $this->assertSame('Season’s greetings, {first_name}!', $email->subject);
        $this->assertSame(1, $email->days_before);
        $this->assertSame(['type' => 'fixed', 'month' => 12, 'day' => 25], $email->date_rule);
    }

    public function test_editing_a_per_country_preset_keeps_its_rule(): void
    {
        $email = LifecycleEmail::where('key', 'festive.mothers_day')->first();
        $rule = $email->date_rule;

        Livewire::actingAs($this->admin)->test(EditLifecycleEmail::class, ['record' => $email->getRouteKey()])
            ->fillForm(['enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals($rule, $email->fresh()->date_rule);
        $this->assertTrue($email->fresh()->enabled);
    }

    public function test_send_test_to_me_mails_the_admin_with_unsaved_changes(): void
    {
        Mail::fake();
        $email = LifecycleEmail::where('key', 'birthday')->first();

        Livewire::actingAs($this->admin)->test(EditLifecycleEmail::class, ['record' => $email->getRouteKey()])
            ->fillForm(['subject' => 'Draft subject for {first_name}'])
            ->callAction('sendTest');

        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($this->admin->email) && str_starts_with($mail->envelope()->subject, 'Draft subject for'));
        $this->assertNotSame('Draft subject for {first_name}', $email->fresh()->subject);
    }

    public function test_support_sub_accounts_need_the_support_department(): void
    {
        $sub = User::factory()->create();
        $sub->assignRole('admin_sub');
        $this->actingAs($sub);
        $this->assertFalse(LifecycleEmailResource::canAccess());

        $sub->givePermissionTo('department.support');
        $this->assertTrue(LifecycleEmailResource::canAccess());
    }
}
