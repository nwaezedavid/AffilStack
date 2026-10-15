<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Notifications\BlogNewsletterConfirmation;
use App\Services\Blog\BlogKit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * POST /api/blog/subscribe — a reader signed up to the newsletter on the
 * blog. Called server-to-server by the AffilStack WordPress theme with the
 * connection key from Admin → Website → Blog, never from a browser.
 *
 * Double opt-in: this only emails a confirmation link (see
 * BlogNewsletterConfirmController); the reader joins the CRM list once
 * they click it.
 */
class BlogSubscribeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! BlogKit::isValidConnectionKey($request->header('X-AffilStack-Blog-Key'))) {
            return response()->json(['message' => 'Invalid connection key.'], 401);
        }

        if (! BlogKit::newsletterEnabled()) {
            return response()->json(['message' => 'The newsletter is switched off.'], 409);
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $email = Str::lower(trim($validated['email']));

        // At most three confirmation emails per address per hour, so the
        // form can't be used to flood someone's inbox.
        $limiterKey = 'blog-newsletter:'.sha1($email);

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            return response()->json(['message' => 'Please check your inbox — we already sent a confirmation link.']);
        }

        RateLimiter::hit($limiterKey, 3600);

        $confirmUrl = URL::temporarySignedRoute('blog.newsletter.confirm', now()->addDays(7), [
            'email' => $email,
            'name' => filled($validated['name'] ?? null) ? Str::limit(trim($validated['name']), 120, '') : null,
            'from' => $validated['source_url'] ?? null,
        ]);

        Notification::route('mail', $email)->notify(new BlogNewsletterConfirmation($confirmUrl));

        return response()->json(['message' => 'Almost done — check your inbox to confirm your subscription.']);
    }
}
