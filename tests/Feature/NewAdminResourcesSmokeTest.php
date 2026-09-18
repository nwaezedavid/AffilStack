<?php

namespace Tests\Feature;

use App\Filament\Resources\BrandLogos\Pages\ListBrandLogos;
use App\Filament\Resources\CreditPackages\Pages\ListCreditPackages;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Filament\Resources\Tutorials\Pages\ListTutorials;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A boot smoke test for the 4 brand-new Filament resources built this
 * session (CreditPackage, BrandLogo, Testimonial, Tutorial) — catches
 * registration/autoload mistakes (missing Page class, wrong icon enum
 * case, etc.) that a narrower unit test wouldn't necessarily exercise.
 * Uses Livewire::test() directly on each List page rather than an HTTP
 * GET on the panel route, the same way AffiliateApplicationTest and
 * PublicContentCachingTest exercise Filament pages elsewhere — a plain
 * HTTP request to any /afs-login/* resource redirects every admin to
 * /afs-login/multi-factor-authentication/set-up (isRequired: true in
 * AdminPanelProvider, confirmed against the pre-existing PlanResource
 * too, so that's expected platform behavior, not something to route
 * around here).
 */
class NewAdminResourcesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_credit_packages_list_page_loads(): void
    {
        Livewire::actingAs($this->admin())->test(ListCreditPackages::class)->assertOk();
    }

    public function test_brand_logos_list_page_loads(): void
    {
        Livewire::actingAs($this->admin())->test(ListBrandLogos::class)->assertOk();
    }

    public function test_testimonials_list_page_loads(): void
    {
        Livewire::actingAs($this->admin())->test(ListTestimonials::class)->assertOk();
    }

    public function test_tutorials_list_page_loads(): void
    {
        Livewire::actingAs($this->admin())->test(ListTutorials::class)->assertOk();
    }
}
