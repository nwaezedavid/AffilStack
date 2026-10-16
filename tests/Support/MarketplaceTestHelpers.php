<?php

namespace Tests\Support;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\DeveloperProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/**
 * Shared fixtures for the developer portal and marketplace tests.
 */
trait MarketplaceTestHelpers
{
    protected function seedMarketplaceRoles(): void
    {
        $this->seed(RolesSeeder::class);
    }

    protected function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('user');

        return $user;
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    protected function developer(array $profile = []): User
    {
        $user = $this->customer();

        DeveloperProfile::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'Acme Labs',
            'slug' => 'acme-labs-'.$user->id,
            'support_email' => 'help@acme.example',
            'status' => 'active',
            'terms_accepted_at' => now(),
        ], $profile));

        return $user->fresh();
    }

    /**
     * An add-on with an approved, live version.
     *
     * @param  array<string, mixed>  $version
     */
    protected function liveAddon(User $developer, string $type = 'app', array $version = [], string $status = 'published'): Addon
    {
        $addon = Addon::create([
            'developer_profile_id' => $developer->developerProfile->id,
            'slug' => Addon::uniqueSlug($version['name'] ?? 'Rank Booster'),
            'type' => $type,
            'status' => $status,
            'published_at' => now(),
        ]);

        $live = $addon->versions()->create(array_merge([
            'version' => '1.0.0',
            'status' => 'approved',
            'name' => 'Rank Booster',
            'tagline' => 'Track where your reviews rank.',
            'description' => str_repeat('Rank Booster watches your review articles and tells you when they move in search. ', 2),
            'category' => 'analytics',
            'icon_path' => 'marketplace/icon.png',
            'pricing' => 'free',
            'price_cents' => 0,
            'reviewed_at' => now(),
        ], $this->typeDefaults($type), $version));

        $addon->update(['live_version_id' => $live->id]);

        return $addon->fresh(['liveVersion', 'developer']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeDefaults(string $type): array
    {
        return match ($type) {
            'app' => [
                'install_url' => 'https://rankbooster.example/connect',
                'privacy_url' => 'https://rankbooster.example/privacy',
                'redirect_uris' => ['https://rankbooster.example/callback'],
                'scopes' => ['profile:read', 'offers:read', 'crm:read'],
            ],
            'template_pack' => ['content' => ['items' => [
                ['kind' => 'email', 'title' => 'Cold email: first touch', 'body' => 'Hi [NAME], I noticed [PRODUCT]…'],
                ['kind' => 'hook', 'title' => 'Curiosity hook', 'body' => 'Nobody tells you this about [PRODUCT].'],
            ]]],
            default => ['content' => [
                'platform' => 'zapier',
                'share_url' => 'https://zapier.com/shared/abc',
                'blueprint' => '{"zap":"lead-to-crm"}',
                'blueprint_name' => 'lead-to-crm.json',
                'steps' => "- Open the template\n- Paste your AffilStack API key\n- Turn it on",
            ]],
        };
    }

    protected function versionOf(Addon $addon): AddonVersion
    {
        return $addon->versions()->latest('id')->firstOrFail();
    }
}
