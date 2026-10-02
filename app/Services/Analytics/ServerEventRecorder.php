<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Support\Analytics\ConsentState;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Agent 04: records events that happen on the server — login, checkout
 * started, signup completed, payments, and dashboard feature use (research
 * started, content generated per module, campaign sent, extension used).
 * Hooked from model events/listeners (see AnalyticsServiceProvider), so no
 * other feature's code had to change.
 *
 * Consent decides how much is kept: with analytics consent (this browser's
 * cookie when the request belongs to the same user, otherwise the user's
 * latest recorded choice) the event is tied to the user and their visitor;
 * without it only an anonymous aggregate count is stored. Recording never
 * throws — analytics must not break a payment or a content generation.
 */
class ServerEventRecorder
{
    public function __construct(protected AnalyticsTracker $tracker) {}

    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function record(?User $user, string $name, string $category, ?array $properties = null, ?int $valueCents = null, ?string $currency = null, ?string $dedupeKey = null, ?string $path = null): ?AnalyticsEvent
    {
        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            $consent = $this->consentFor($user, $request);
            $now = now();

            if (! $user || ! $consent->allowsAnalytics()) {
                return $this->tracker->storeEvent(null, null, null, $name, $category, null, $properties, $now, $valueCents, $currency, $dedupeKey);
            }

            $visitor = $this->visitorFromRequest($request, $user);
            $session = $visitor ? $this->tracker->activeSession($visitor, $now) : null;

            return $this->tracker->storeEvent($visitor, $session, $user->id, $name, $category, $path, $properties, $now, $valueCents, $currency, $dedupeKey);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function consentFor(?User $user, ?Request $request): ConsentState
    {
        if ($request && $user && $request->user()?->is($user)) {
            $fromCookie = ConsentState::fromRequest($request);

            if ($fromCookie->isCurrent()) {
                return $fromCookie;
            }
        }

        if (! $user) {
            return ConsentState::none();
        }

        return ConsentState::fromRecord(ConsentRecord::latestForUser($user->id));
    }

    protected function visitorFromRequest(?Request $request, User $user): ?AnalyticsVisitor
    {
        $uuid = $request?->cookies->get((string) config('analytics.cookies.visitor'));

        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            return null;
        }

        return AnalyticsVisitor::query()
            ->where('uuid', strtolower($uuid))
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->first();
    }
}
