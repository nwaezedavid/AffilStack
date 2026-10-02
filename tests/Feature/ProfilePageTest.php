<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function personal(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'birth_day' => '',
            'birth_month' => '',
            'birth_year' => '',
            'phone_country' => 'NG',
            'phone_number' => '',
            'gender' => '',
            'religion' => '',
        ], $overrides);
    }

    public function test_the_page_renders_every_tab(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile', ['tab' => 'business']))
            ->assertOk()
            ->assertSee('id="panel-personal"', false)
            ->assertSee('id="panel-business"', false)
            ->assertSee('id="panel-security"', false)
            ->assertSee('id="panel-notifications"', false)
            ->assertSee('Your privacy matters')
            ->assertSee('Birthday, holiday and tips emails')
            ->assertSee('Enable two-factor authentication');
    }

    public function test_personal_details_save_with_a_birthday_without_a_year(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.personal.update'), $this->personal($user, [
            'name' => 'Ada Obi',
            'birth_day' => 14,
            'birth_month' => 11,
            'phone_country' => 'NG',
            'phone_number' => '0803 123 4567',
            'gender' => 'female',
            'religion' => 'christianity',
        ]))->assertRedirect(route('profile', ['tab' => 'personal']));

        $user->refresh();
        $this->assertSame('Ada Obi', $user->name);
        $this->assertSame('1904-11-14', $user->birth_date->toDateString());
        $this->assertSame('+234 803 123 4567', $user->phone);
        $this->assertSame('christianity', $user->religion);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertDontSee('<option value="1904" selected', false);
    }

    public function test_a_full_birth_date_and_leap_day_are_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.personal.update'), $this->personal($user, ['birth_day' => 29, 'birth_month' => 2]))->assertSessionHasNoErrors();
        $this->assertSame('1904-02-29', $user->fresh()->birth_date->toDateString());

        $this->actingAs($user)->put(route('profile.personal.update'), $this->personal($user, ['birth_day' => 3, 'birth_month' => 7, 'birth_year' => 1991]))->assertSessionHasNoErrors();
        $this->assertSame('1991-07-03', $user->fresh()->birth_date->toDateString());
    }

    public function test_impossible_or_incomplete_birthdays_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.personal.update'), $this->personal($user, ['birth_day' => 31, 'birth_month' => 2]))
            ->assertSessionHasErrorsIn('personal', 'birth_day');
        $this->actingAs($user)->put(route('profile.personal.update'), $this->personal($user, ['birth_day' => 29, 'birth_month' => 2, 'birth_year' => 2001]))
            ->assertSessionHasErrorsIn('personal', 'birth_day');
        $this->actingAs($user)->put(route('profile.personal.update'), $this->personal($user, ['birth_day' => 12]))
            ->assertSessionHasErrorsIn('personal', 'birth_month');

        $this->assertNull($user->fresh()->birth_date);
    }

    public function test_personal_validation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->put(route('profile.personal.update'), $this->personal($user, [
            'email' => $other->email,
            'religion' => 'pastafarian',
            'gender' => 'robot',
            'phone_number' => 'call me maybe',
        ]))->assertSessionHasErrorsIn('personal', ['email', 'religion', 'gender', 'phone_number']);
    }

    public function test_business_details_save_and_fill_the_timezone_from_the_country(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.business.update'), [
            'company_name' => 'Obi Media',
            'business_website' => 'obimedia.ng',
            'business_industry' => 'Marketing agency',
            'country' => 'KE',
            'state_region' => 'Nairobi County',
            'city' => 'Nairobi',
            'timezone' => '',
        ])->assertRedirect(route('profile', ['tab' => 'business']));

        $user->refresh();
        $this->assertSame('https://obimedia.ng', $user->business_website);
        $this->assertSame('KE', $user->country);
        $this->assertSame('Africa/Nairobi', $user->timezone);
    }

    public function test_business_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.business.update'), [
            'country' => 'XX',
            'timezone' => 'Mars/Olympus',
            'business_website' => 'not a url at all',
        ])->assertSessionHasErrorsIn('business', ['country', 'timezone', 'business_website']);
    }

    public function test_a_profile_photo_is_cropped_to_a_square_webp(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 900, 600),
        ])->assertRedirect(route('profile', ['tab' => 'personal']));

        $path = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([512, 512], [$size[0], $size[1]]);
        $this->assertSame('image/webp', $size['mime']);

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('new.png', 300, 300)]);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame([300, 300], array_slice(getimagesizefromstring(Storage::disk('public')->get($user->fresh()->avatar_path)), 0, 2));
    }

    public function test_non_images_and_tiny_images_are_rejected_and_photos_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrorsIn('photo', 'photo');
        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('tiny.png', 20, 20)])
            ->assertSessionHasErrorsIn('photo', 'photo');
        $this->assertNull($user->fresh()->avatar_path);

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('ok.jpg', 200, 200)]);
        $path = $user->fresh()->avatar_path;
        $this->actingAs($user)->delete(route('profile.photo.destroy'))->assertRedirect();
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_notification_preferences_save(): void
    {
        $user = User::factory()->create(['notify_email_on_completion' => true]);

        $this->actingAs($user)->put(route('profile.notifications.update'), ['notify_email_on_completion' => '1'])
            ->assertRedirect(route('profile', ['tab' => 'notifications']));

        $user->refresh();
        $this->assertTrue($user->notify_email_on_completion);
        $this->assertFalse($user->marketingEmailsEnabled());

        $this->actingAs($user)->put(route('profile.notifications.update'), ['marketing_emails_opt_in' => '1']);
        $this->assertTrue($user->fresh()->marketingEmailsEnabled());
        $this->assertFalse($user->fresh()->notify_email_on_completion);
    }

    public function test_password_change_still_works_from_the_security_tab(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)->from(route('profile', ['tab' => 'security']))->put(route('user-password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password-456',
            'password_confirmation' => 'brand-new-password-456',
        ])->assertRedirect(route('profile', ['tab' => 'security']));

        $this->assertTrue(Hash::check('brand-new-password-456', $user->fresh()->password));
    }

    public function test_team_seats_and_affiliate_only_accounts_can_edit_their_own_profile(): void
    {
        $owner = User::factory()->create();
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'Systeme.io', 'product_url' => 'https://systeme.io', 'affiliate_network' => 'Direct']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $affiliate = User::factory()->create(['is_affiliate_only' => true]);

        foreach ([$seat, $affiliate] as $account) {
            $this->actingAs($account)->get(route('profile'))->assertOk();
            $this->actingAs($account)->put(route('profile.personal.update'), $this->personal($account, ['name' => 'Renamed']))->assertSessionHasNoErrors();
            $this->assertSame('Renamed', $account->fresh()->name);
        }
    }
}
