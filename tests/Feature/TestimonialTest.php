<?php

namespace Tests\Feature;

use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "The testimonial section will appear as soon as I have a minimum of 3
 * updated in the admin dashboard area." See Testimonial::published() for
 * the exact threshold logic this test file is built around.
 */
class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_homepage_hides_the_section_with_zero_testimonials(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('What affiliates are saying');
    }

    public function test_homepage_hides_the_section_with_only_two_published_testimonials(): void
    {
        Testimonial::factory()->count(2)->create(['is_published' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('What affiliates are saying');
    }

    public function test_homepage_shows_the_section_once_three_are_published(): void
    {
        Testimonial::factory()->create(['author_name' => 'Jamie Rivera', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Priya Shah', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Lee Brooks', 'is_published' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('What affiliates are saying');
        $response->assertSee('Jamie Rivera');
        $response->assertSee('Priya Shah');
        $response->assertSee('Lee Brooks');
    }

    public function test_unpublished_testimonials_never_count_toward_the_minimum_or_display(): void
    {
        Testimonial::factory()->create(['author_name' => 'Published One', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Published Two', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Draft Person', 'is_published' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('What affiliates are saying');
        $response->assertDontSee('Draft Person');
    }

    public function test_the_published_list_is_cached_and_busted_on_save(): void
    {
        Testimonial::factory()->count(3)->create(['is_published' => true]);

        $first = Testimonial::published();
        $this->assertCount(3, $first);

        DB::enableQueryLog();
        Testimonial::published();
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        // Unpublishing one drops the count below the minimum — the whole
        // section should disappear, not just show 2.
        Testimonial::first()->update(['is_published' => false]);
        $this->assertCount(0, Testimonial::published());
    }

    public function test_a_department_scoped_sub_account_without_content_access_cannot_reach_the_resource(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(TestimonialResource::canAccess());

        $subAccount->syncPermissions(['department.content']);
        $this->assertTrue(TestimonialResource::canAccess());
    }

    /**
     * "I want users to be able to submit their review from their dashboard
     * and it will then appear in the admin dashboard area where I can
     * review, edit and approve it." See TestimonialController for the
     * dashboard side of this.
     */
    public function test_a_customer_can_submit_a_testimonial_from_their_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Alex Customer']);
        $user->assignRole('user');

        $response = $this->actingAs($user)->post(route('testimonial.update'), [
            'author_name' => 'Alex C.',
            'author_role' => 'Affiliate marketer',
            'quote' => 'AffilStack changed how I run my whole business.',
            'rating' => 5,
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $testimonial = Testimonial::where('user_id', $user->id)->first();
        $this->assertNotNull($testimonial);
        $this->assertTrue($testimonial->isPending());
        $this->assertFalse($testimonial->is_published);
        $this->assertSame('Alex C.', $testimonial->author_name);
    }

    public function test_a_pending_submission_never_counts_toward_the_homepage_even_at_the_minimum(): void
    {
        Testimonial::factory()->count(2)->create(['is_published' => true]);
        Testimonial::factory()->pending()->create(['author_name' => 'Awaiting Review']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('What affiliates are saying');
        $response->assertDontSee('Awaiting Review');
    }

    public function test_resubmitting_sends_an_already_approved_testimonial_back_to_pending(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $existing = Testimonial::factory()->create([
            'user_id' => $user->id,
            'quote' => 'Original quote.',
            'status' => Testimonial::STATUS_APPROVED,
            'is_published' => true,
        ]);

        $this->actingAs($user)->post(route('testimonial.update'), [
            'author_name' => $existing->author_name,
            'quote' => 'An updated, different quote.',
            'rating' => 4,
        ]);

        $existing->refresh();
        $this->assertTrue($existing->isPending());
        $this->assertFalse($existing->is_published);
        $this->assertSame('An updated, different quote.', $existing->quote);

        // Still only one row for this user — an update, not a duplicate.
        $this->assertSame(1, Testimonial::where('user_id', $user->id)->count());
    }

    public function test_an_affiliate_only_account_cannot_submit_a_testimonial(): void
    {
        $affiliate = User::factory()->create(['is_affiliate_only' => true]);
        $affiliate->assignRole('user');

        $this->actingAs($affiliate)->get(route('testimonial.edit'))->assertForbidden();
    }

    public function test_an_admin_can_approve_a_pending_testimonial_from_the_table(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $pending = Testimonial::factory()->pending()->create();

        Livewire::actingAs($admin)
            ->test(ListTestimonials::class)
            ->callTableAction('approve', $pending);

        $pending->refresh();
        $this->assertTrue($pending->status === Testimonial::STATUS_APPROVED);
        $this->assertTrue($pending->is_published);
    }

    public function test_an_admin_can_decline_a_pending_testimonial_from_the_table(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $pending = Testimonial::factory()->pending()->create();

        Livewire::actingAs($admin)
            ->test(ListTestimonials::class)
            ->callTableAction('decline', $pending);

        $pending->refresh();
        $this->assertSame(Testimonial::STATUS_DECLINED, $pending->status);
        $this->assertFalse($pending->is_published);
    }

    public function test_the_navigation_badge_reflects_the_pending_count(): void
    {
        $this->assertNull(TestimonialResource::getNavigationBadge());

        Testimonial::factory()->pending()->create();
        Testimonial::factory()->pending()->create();

        $this->assertSame('2', TestimonialResource::getNavigationBadge());
    }
}
