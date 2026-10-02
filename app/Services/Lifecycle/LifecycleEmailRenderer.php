<?php

namespace App\Services\Lifecycle;

use App\Models\LifecycleEmail;
use App\Models\Offer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Countries;
use App\Support\Lifecycle\UserActivity;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Turns a LifecycleEmail + a recipient into the finished, personalised
 * email content: placeholder substitution, {if:…}/{unless:…} blocks, and
 * Markdown → email-safe HTML with inline styles (email clients ignore
 * <style> blocks far too often to rely on them). Used for real sends, the
 * admin live preview and "Send test to me", so all three always match.
 *
 * Placeholders: {first_name} {name} {business_name} {site_name}
 * {dashboard_url} {profile_url} {credits} {bonus_credits} {product_name}
 * {days_inactive} {festival_name} {country_name} {year}
 */
class LifecycleEmailRenderer
{
    /**
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        'first_name' => 'First name (or "there")',
        'name' => 'Full name',
        'business_name' => 'Business name (or "your business")',
        'site_name' => 'Platform name',
        'dashboard_url' => 'Link to their dashboard',
        'profile_url' => 'Link to their profile',
        'credits' => 'Credits left',
        'bonus_credits' => 'Birthday gift credits',
        'product_name' => 'Product they last researched',
        'days_inactive' => 'Days since last active',
        'festival_name' => 'This email\'s name',
        'country_name' => 'Their country',
        'year' => 'Current year',
    ];

    /**
     * @param  array<string, mixed>  $context  extra/overriding raw values, e.g. ['bonus_credits' => 50]
     * @return array{subject: string, preheader: string, heading: string, body_html: string, cta_label: ?string, cta_url: ?string, hero_url: ?string, theme_color: string, unsubscribe_url: ?string, site_name: string, logo_url: ?string, profile_url: string}
     */
    public function render(LifecycleEmail $email, User $user, array $context = []): array
    {
        $raw = $this->rawValues($email, $user, $context);
        $display = $this->displayValues($raw);

        $ctaUrl = $email->cta_label ? $this->substitute((string) $email->cta_url, $display) : null;

        if ($ctaUrl !== null && ! preg_match('#^https?://#i', $ctaUrl)) {
            $ctaUrl = $display['dashboard_url'];
        }

        return [
            'subject' => $this->plain($this->substitute($this->conditionals($email->subject, $raw), $display)),
            'preheader' => $this->plain($this->substitute($this->conditionals((string) $email->preheader, $raw), $display)),
            'heading' => $this->plain($this->substitute($this->conditionals($email->heading, $raw), $display)),
            'body_html' => $this->bodyHtml($email->body, $raw, $display),
            'cta_label' => $email->cta_label ? $this->plain($this->substitute($email->cta_label, $display)) : null,
            'cta_url' => $ctaUrl,
            'hero_url' => $email->hero_image_path ? Storage::disk('public')->url($email->hero_image_path) : null,
            'theme_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $email->theme_color) ? $email->theme_color : '#2452D9',
            'unsubscribe_url' => $user->exists ? $this->unsubscribeUrl($user) : null,
            'site_name' => $display['site_name'],
            'logo_url' => ($logo = SiteSetting::get('logo_rectangular_path')) ? Storage::disk('public')->url($logo) : null,
            'profile_url' => $display['profile_url'],
        ];
    }

    /**
     * Signed, non-expiring one-click unsubscribe link (also used for the
     * List-Unsubscribe header).
     */
    public function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('lifecycle.unsubscribe', ['user' => $user->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function rawValues(LifecycleEmail $email, User $user, array $context): array
    {
        $lastSeen = $user->exists ? UserActivity::lastSeen($user) : null;

        $values = [
            'first_name' => Str::of((string) $user->name)->trim()->explode(' ')->filter()->first(),
            'name' => trim((string) $user->name),
            'business_name' => trim((string) $user->company_name),
            'site_name' => (string) SiteSetting::get('site_name', 'AffilStack'),
            'dashboard_url' => route('dashboard'),
            'profile_url' => route('profile'),
            'credits' => $user->exists ? $user->billableUser()->credits_balance : (int) $user->credits_balance,
            'bonus_credits' => (int) $email->bonus_credits,
            'product_name' => $user->exists ? $this->latestProductName($user) : null,
            'days_inactive' => $lastSeen ? (int) $lastSeen->diffInDays(now()) : null,
            'festival_name' => $email->name,
            'country_name' => Countries::name(Countries::normalize($user->country)),
            'year' => now()->year,
        ];

        return array_merge($values, $context);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    protected function displayValues(array $raw): array
    {
        $display = array_map(fn ($value) => (string) ($value ?? ''), $raw);

        $display['first_name'] = $display['first_name'] !== '' ? $display['first_name'] : 'there';
        $display['name'] = $display['name'] !== '' ? $display['name'] : $display['first_name'];
        $display['business_name'] = $display['business_name'] !== '' ? $display['business_name'] : 'your business';
        $display['product_name'] = $display['product_name'] !== '' ? $display['product_name'] : 'your product';
        $display['country_name'] = $display['country_name'] !== '' ? $display['country_name'] : 'your country';
        $display['credits'] = number_format((int) $raw['credits']);
        $display['bonus_credits'] = number_format((int) $raw['bonus_credits']);

        return $display;
    }

    protected function latestProductName(User $user): ?string
    {
        return Offer::query()
            ->where('user_id', $user->billableUser()->id)
            ->where('is_sandbox', false)
            ->whereNotNull('product_name')
            ->latest('updated_at')
            ->value('product_name');
    }

    /**
     * {if:key}…{/if} keeps its content only when the raw value is set and
     * not zero; {unless:key}…{/unless} is the opposite.
     *
     * @param  array<string, mixed>  $raw
     */
    public function conditionals(string $text, array $raw): string
    {
        $truthy = fn (string $key) => ! in_array($raw[$key] ?? null, [null, '', 0, '0', false], true);

        $text = preg_replace_callback('/\{if:(\w+)\}(.*?)\{\/if\}/s', fn ($m) => $truthy($m[1]) ? $m[2] : '', $text) ?? $text;

        return preg_replace_callback('/\{unless:(\w+)\}(.*?)\{\/unless\}/s', fn ($m) => $truthy($m[1]) ? '' : $m[2], $text) ?? $text;
    }

    /**
     * @param  array<string, string>  $display
     */
    protected function substitute(string $text, array $display, bool $escapeMarkdown = false): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($display, $escapeMarkdown) {
            if (! array_key_exists($m[1], $display)) {
                return $m[0];
            }

            $value = $display[$m[1]];

            return $escapeMarkdown && ! str_ends_with($m[1], '_url') ? addcslashes($value, '\\`*_[]<>#') : $value;
        }, $text) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, string>  $display
     */
    protected function bodyHtml(string $body, array $raw, array $display): string
    {
        $markdown = $this->substitute($this->conditionals($body, $raw), $display, escapeMarkdown: true);
        $markdown = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $markdown)) ?? $markdown);

        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        $styles = [
            '<p>' => '<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#334155;">',
            '<ul>' => '<ul style="margin:0 0 16px;padding-left:22px;font-size:16px;line-height:1.65;color:#334155;">',
            '<ol>' => '<ol style="margin:0 0 16px;padding-left:22px;font-size:16px;line-height:1.65;color:#334155;">',
            '<li>' => '<li style="margin:0 0 8px;">',
            '<strong>' => '<strong style="color:#0B1E3D;font-weight:700;">',
            '<h2>' => '<h2 style="margin:24px 0 12px;font-size:20px;line-height:1.3;color:#0B1E3D;">',
            '<h3>' => '<h3 style="margin:20px 0 10px;font-size:17px;line-height:1.3;color:#0B1E3D;">',
            '<blockquote>' => '<blockquote style="margin:0 0 16px;padding:12px 16px;border-left:4px solid #E5B93C;background:#FFF9E8;color:#334155;">',
            '<a href=' => '<a style="color:#2452D9;text-decoration:underline;" href=',
        ];

        return str_replace(array_keys($styles), array_values($styles), $html);
    }

    protected function plain(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? $text);
    }
}
