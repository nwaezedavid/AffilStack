<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\PageView;
use App\Models\User;
use App\Support\Analytics\ConsentState;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Agent 04: links an anonymous visitor to the account it turns out to be —
 * on login (any login path fires Illuminate\Auth\Events\Login), and when a
 * checkout started in this browser is paid (matched by the hashed email
 * typed at checkout). Everything that visitor did before is back-filled
 * with the user id, so the admin's journey view shows the path before AND
 * after purchase. Only ever links a visitor that is still unclaimed: a
 * shared computer never merges two people's histories.
 */
class IdentityStitcher
{
    public function stitchFromRequest(User $user, Request $request): ?AnalyticsVisitor
    {
        $consent = ConsentState::fromRequest($request);

        // The consent choice itself (even a refusal) is linked to the
        // account, so "this user's current choice" is answerable later.
        if ($consent->consentId) {
            ConsentRecord::query()->where('consent_id', $consent->consentId)->whereNull('user_id')->update(['user_id' => $user->id]);
        }

        if (! $consent->allowsAnalytics()) {
            return null;
        }

        $uuid = $request->cookies->get((string) config('analytics.cookies.visitor'));

        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            return null;
        }

        $visitor = AnalyticsVisitor::query()->where('uuid', strtolower($uuid))->first();

        return $visitor ? $this->stitch($visitor, $user) : null;
    }

    /**
     * Visitors that started a checkout with this email (hashed) and haven't
     * been claimed yet — called when that checkout is paid.
     */
    public function stitchByEmailHash(User $user, string $emailSha256): void
    {
        AnalyticsVisitor::query()
            ->where('email_sha256', $emailSha256)
            ->whereNull('user_id')
            ->get()
            ->each(fn (AnalyticsVisitor $visitor) => $this->stitch($visitor, $user));
    }

    public function stitch(AnalyticsVisitor $visitor, User $user): AnalyticsVisitor
    {
        if ($visitor->user_id !== null) {
            return $visitor;
        }

        $visitor->forceFill(['user_id' => $user->id, 'identified_at' => now()])->save();

        AnalyticsSession::query()->where('visitor_id', $visitor->id)->whereNull('user_id')->update(['user_id' => $user->id]);
        AnalyticsEvent::query()->where('visitor_id', $visitor->id)->whereNull('user_id')->update(['user_id' => $user->id]);
        PageView::query()->where('visitor_id', $visitor->id)->whereNull('user_id')->update(['user_id' => $user->id]);
        ConsentRecord::query()->where('visitor_id', $visitor->id)->whereNull('user_id')->update(['user_id' => $user->id]);

        // The anonymous profile is folded into the user's on the next build.
        AnalyticsProfile::query()->where('visitor_id', $visitor->id)->delete();

        return $visitor;
    }
}
