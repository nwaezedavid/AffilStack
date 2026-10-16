<?php

namespace App\Services\Marketplace;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\DeveloperProfile;
use App\Models\User;
use App\Notifications\AddonReviewed;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An add-on's life before customers see it: created as a draft, submitted,
 * approved (or sent back) by an admin. Every change to a live add-on is a
 * new version that goes through the same review.
 */
class AddonReviewService
{
    public function create(DeveloperProfile $developer, string $type, string $name): Addon
    {
        return DB::transaction(function () use ($developer, $type, $name) {
            $addon = Addon::create([
                'developer_profile_id' => $developer->id,
                'slug' => Addon::uniqueSlug($name),
                'type' => $type,
                'status' => 'draft',
            ]);

            $addon->versions()->create([
                'version' => '1.0.0',
                'status' => 'draft',
                'name' => $name,
                'tagline' => '',
                'description' => '',
                'category' => $type === 'automation' ? 'automation' : 'other',
                'pricing' => 'free',
                'price_cents' => 0,
                'content' => $type === 'app' ? null : [],
            ]);

            if ($type === 'app') {
                $addon->issueClientCredentials();
            }

            return $addon;
        });
    }

    /**
     * The version to edit: the current draft or sent-back version, or a
     * new draft copied from the live one.
     */
    public function draftFor(Addon $addon): AddonVersion
    {
        $working = $addon->workingVersion()->first();

        if ($working) {
            return $working;
        }

        $live = $addon->liveVersion ?? $addon->versions()->latest('id')->firstOrFail();

        return $addon->versions()->create(array_merge(
            $live->only((new AddonVersion)->getFillable()),
            [
                'version' => $this->nextVersion($live->version),
                'status' => 'draft',
                'changelog' => null,
                'review_note' => null,
                'submitted_at' => null,
                'reviewed_at' => null,
                'reviewed_by_id' => null,
            ],
        ));
    }

    /**
     * @throws ValidationException when the listing isn't complete
     */
    public function submit(AddonVersion $version): void
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages(['version' => 'This version has already been submitted.']);
        }

        $problems = $this->problems($version);

        if ($problems !== []) {
            throw ValidationException::withMessages(['version' => $problems]);
        }

        $version->update(['status' => 'submitted', 'submitted_at' => now(), 'review_note' => null]);

        $admins = User::role(['admin', 'super-admin'])->get();

        if ($admins->isNotEmpty()) {
            Notification::make()
                ->title('Add-on waiting for review: '.$version->name.' '.$version->version)
                ->body('Review it in Marketplace → Add-on reviews.')
                ->info()
                ->sendToDatabase($admins);
        }
    }

    /**
     * What's missing before a version can be submitted.
     *
     * @return array<int, string>
     */
    public function problems(AddonVersion $version): array
    {
        $addon = $version->addon;
        $problems = [];

        if (trim($version->tagline) === '') {
            $problems[] = 'Add a short tagline.';
        }

        if (mb_strlen(trim($version->description)) < 80) {
            $problems[] = 'Write a description of at least 80 characters: what it does and who it is for.';
        }

        if (! $version->icon_path) {
            $problems[] = 'Upload an icon.';
        }

        if ($version->isPaid() && ($version->price_cents < (int) config('marketplace.min_price_cents') || $version->price_cents > (int) config('marketplace.max_price_cents'))) {
            $problems[] = 'Set a monthly price between $'.number_format(config('marketplace.min_price_cents') / 100, 2).' and $'.number_format(config('marketplace.max_price_cents') / 100, 2).'.';
        }

        if ($addon->isApp()) {
            if (empty($version->redirect_uris)) {
                $problems[] = 'Add at least one redirect URL for "Connect with AffilStack".';
            }

            if (empty($version->scopes)) {
                $problems[] = 'Choose the permissions your app needs.';
            }

            if (! $version->install_url) {
                $problems[] = 'Add the address customers start from to connect your app.';
            }

            if (! $version->privacy_url) {
                $problems[] = 'Add a link to your privacy policy: connected apps handle customer data.';
            }
        }

        if ($addon->type === 'template_pack' && count($version->content['items'] ?? []) === 0) {
            $problems[] = 'Add at least one template to the pack.';
        }

        if ($addon->type === 'automation') {
            $content = $version->content ?? [];

            if (empty($content['platform'])) {
                $problems[] = 'Choose the automation platform.';
            }

            if (empty($content['share_url']) && empty($content['blueprint'])) {
                $problems[] = 'Add a template link or a blueprint file customers can import.';
            }

            if (mb_strlen(trim((string) ($content['steps'] ?? ''))) < 20) {
                $problems[] = 'Write the setup steps.';
            }
        }

        return $problems;
    }

    public function approve(AddonVersion $version, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($version, $admin, $note) {
            $updated = AddonVersion::whereKey($version->id)->where('status', 'submitted')->update([
                'status' => 'approved',
                'review_note' => $note,
                'reviewed_at' => now(),
                'reviewed_by_id' => $admin->id,
            ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages(['version' => 'Only a submitted version can be approved.']);
            }

            $addon = $version->addon()->lockForUpdate()->first();

            $addon->versions()->whereKeyNot($version->id)->where('status', 'approved')->update(['status' => 'superseded']);

            $addon->forceFill([
                'live_version_id' => $version->id,
                'status' => in_array($addon->status, ['suspended', 'retired'], true) ? $addon->status : 'published',
                'published_at' => $addon->published_at ?? now(),
            ])->save();

            // Permissions dropped from the app are taken back from every
            // customer straight away; new ones need the customer to
            // connect again.
            if ($addon->isApp()) {
                app(AddonInstaller::class)->limitScopes($addon, $version->scopes ?? []);
            }
        });

        $version->refresh();
        $version->addon->developer->user->notify(new AddonReviewed($version));
    }

    public function reject(AddonVersion $version, User $admin, string $note): void
    {
        $updated = AddonVersion::whereKey($version->id)->where('status', 'submitted')->update([
            'status' => 'rejected',
            'review_note' => $note,
            'reviewed_at' => now(),
            'reviewed_by_id' => $admin->id,
        ]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['version' => 'Only a submitted version can be sent back.']);
        }

        $version->refresh();
        $version->addon->developer->user->notify(new AddonReviewed($version));
    }

    protected function nextVersion(string $version): string
    {
        $parts = array_map('intval', array_pad(explode('.', preg_replace('/[^0-9.]/', '', $version) ?: '1.0.0'), 3, 0));
        $parts[2]++;

        return implode('.', array_slice($parts, 0, 3));
    }
}
