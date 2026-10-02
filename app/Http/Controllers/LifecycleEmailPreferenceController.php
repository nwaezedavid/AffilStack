<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * One-click unsubscribe from lifecycle emails (birthday, festive season,
 * re-engagement). Every link is a signed URL generated per user (see
 * LifecycleEmailRenderer::unsubscribeUrl()), so no login is needed and
 * nobody can unsubscribe someone else by guessing an ID. GET unsubscribes
 * straight away (the link in the email footer); POST does the same for
 * mail clients' native "Unsubscribe" button (RFC 8058 List-Unsubscribe-
 * Post), which is why that route skips CSRF. The resubscribe button on the
 * confirmation page reverses it. Account emails (receipts, security,
 * support replies) are unaffected.
 */
class LifecycleEmailPreferenceController extends Controller
{
    public function unsubscribe(User $user): View
    {
        $user->forceFill(['marketing_emails_opt_in' => false])->saveQuietly();

        return view('lifecycle.unsubscribed', [
            'user' => $user,
            'resubscribeUrl' => URL::signedRoute('lifecycle.resubscribe', ['user' => $user->getKey()]),
            'subscribed' => false,
        ]);
    }

    public function oneClickUnsubscribe(User $user): Response
    {
        $user->forceFill(['marketing_emails_opt_in' => false])->saveQuietly();

        return response('Unsubscribed.', 200, ['Content-Type' => 'text/plain']);
    }

    public function resubscribe(User $user): View|RedirectResponse
    {
        $user->forceFill(['marketing_emails_opt_in' => true])->saveQuietly();

        return view('lifecycle.unsubscribed', [
            'user' => $user,
            'resubscribeUrl' => null,
            'subscribed' => true,
        ]);
    }
}
