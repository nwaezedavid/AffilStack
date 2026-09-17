<?php

namespace App\Services\Social;

use App\Models\Generation;
use App\Models\SocialConnection;

/**
 * Task #2: a social platform AffilStack can actually publish a rendered
 * UGC video to on a user's behalf, once connected — YouTube, TikTok,
 * Instagram. Distinct from the plain SocialOAuthProvider (LinkedIn,
 * task #3) which only ever connects for identity/export context and never
 * posts anything. Every implementation is admin-gated by
 * isApprovedForPublishing() — each of these platforms restricts real
 * publish access behind its own app review/audit, so until an admin
 * marks that approved, Dashboard\SocialPublishController falls back to
 * "download and post manually" instead of attempting publish().
 */
interface SocialPublishProvider extends SocialOAuthProvider
{
    public function isApprovedForPublishing(): bool;

    /**
     * Publishes $generation's rendered video (Generation::output, a
     * publicly reachable URL — see UgcVideoService) using $connection's
     * stored credentials. Throws RuntimeException with a user-facing
     * message on failure.
     *
     * @return array{url: ?string, external_id: ?string}
     */
    public function publish(SocialConnection $connection, Generation $generation, string $title, string $caption): array;
}
