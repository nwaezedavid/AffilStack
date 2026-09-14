<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Notifications\ContactMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('marketing.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $contactMessage = ContactMessage::create([
            ...$validated,
            'ip_address' => $request->ip(),
        ]);

        $supportEmail = SiteSetting::get('support_email') ?: config('mail.from.address');

        if ($supportEmail) {
            Notification::route('mail', $supportEmail)->notify(new ContactMessageReceived($contactMessage));
        }

        return back()->with('success', "Thanks, {$contactMessage->name} — we've received your message and will reply by email soon.");
    }
}
