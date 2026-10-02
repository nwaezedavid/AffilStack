<?php

use App\Models\SitePage;
use App\Support\Analytics\CookiePolicyContent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Agent 04: brings an existing site's legal pages in line with the new
     * consent system without overwriting anything an admin wrote:
     * - the Cookie Policy is replaced only if it is still the original
     *   seeded starter text (compared whitespace-insensitively);
     * - one short cookie paragraph is appended to the Privacy Policy's
     *   cookie section, once.
     */
    public function up(): void
    {
        $policy = SitePage::query()->where('slug', 'cookie-policy')->first();

        if ($policy && $this->normalize((string) $policy->content) === $this->normalize(self::ORIGINAL_COOKIE_POLICY)) {
            $policy->forceFill(['content' => CookiePolicyContent::html()])->save();
        }

        $privacy = SitePage::query()->where('slug', 'privacy')->first();
        $anchor = 'Cookie Policy</a> for details and how to control them.</p>';

        if ($privacy && ! str_contains((string) $privacy->content, CookiePolicyContent::PRIVACY_MARKER) && str_contains((string) $privacy->content, $anchor)) {
            $privacy->forceFill([
                'content' => preg_replace('/'.preg_quote($anchor, '/').'/', $anchor."\n".CookiePolicyContent::privacyAddendum(), (string) $privacy->content, 1),
            ])->save();
        }
    }

    public function down(): void
    {
        // Content-only change; nothing to roll back structurally.
    }

    protected function normalize(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }

    private const ORIGINAL_COOKIE_POLICY = <<<'HTML'
        <p>This Cookie Policy explains what cookies AffilStack uses and why.</p> <h2>What are cookies</h2> <p>Cookies are small text files stored on your device that help a website remember information about your visit.</p> <h2>Cookies we use</h2> <ul> <li><strong>Essential cookies:</strong> required to keep you signed in and to remember basic security tokens (such as CSRF protection). The Platform cannot function without these.</li> <li><strong>Analytics cookies:</strong> help us understand how visitors use the Platform (for example, via Google Analytics, where enabled) so we can improve it. These are only set if analytics is enabled in our site settings.</li> <li><strong>Advertising cookies:</strong> may be used on our marketing pages to measure the effectiveness of ad campaigns that brought you to the site.</li> </ul> <h2>Controlling cookies</h2> <p>Most browsers let you block or delete cookies through their settings. Blocking essential cookies will prevent you from signing in or using the dashboard. Blocking analytics or advertising cookies will not affect your ability to use the Platform.</p> <h2>Changes to this policy</h2> <p>We may update this Cookie Policy as our use of cookies changes. Check back periodically for updates.</p> <h2>Contact</h2> <p>Questions about this Cookie Policy can be sent through our <a href="/contact">Contact page</a>.</p>
        HTML;
};
