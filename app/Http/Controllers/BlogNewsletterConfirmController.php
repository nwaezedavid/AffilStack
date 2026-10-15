<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use App\Services\Blog\BlogFeed;
use App\Services\Blog\BlogKit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The link in the newsletter confirmation email (signed, valid 7 days).
 * Adds the reader as a CRM contact of the chosen list's owner — matched
 * by email if they're already there — and adds them to the list, which
 * starts any autopilot campaign on it. Then sends them back to the blog.
 */
class BlogNewsletterConfirmController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $list = BlogKit::newsletterEnabled() ? BlogKit::newsletterList() : null;
        $back = BlogFeed::baseUrl() ?? route('home');

        if ($list === null) {
            return redirect()->away($back);
        }

        $email = Str::lower(trim((string) $request->query('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(404);
        }

        $contact = CrmContact::query()
            ->where('user_id', $list->user_id)
            ->where('email', $email)
            ->first();

        if ($contact === null) {
            $name = trim((string) $request->query('name'));
            $from = (string) $request->query('from');

            $contact = CrmContact::create([
                'user_id' => $list->user_id,
                'email' => $email,
                'name' => $name !== '' ? Str::limit($name, 120, '') : null,
                'source' => 'blog',
                'status' => 'new',
                'notes' => 'Subscribed to the newsletter on the blog'.(preg_match('#^https?://#i', $from) ? ': '.Str::limit($from, 500, '') : '.'),
            ]);
        } elseif ($contact->unsubscribed_at !== null) {
            // They confirmed from their own inbox: a fresh opt-in.
            $contact->forceFill(['unsubscribed_at' => null])->save();
        }

        $list->addContact($contact);

        return redirect()->away($back.'/?subscribed=1');
    }
}
