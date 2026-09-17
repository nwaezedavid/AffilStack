<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\EmailConnection;
use App\Services\Crm\GmailOAuthService;
use App\Services\Crm\PersonalEmailSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * Task #1: the dashboard side of connecting a user's own Gmail or SMTP for
 * CRM nurture sending — see CrmEmailService for how a connection, once
 * verified, takes over sending exclusively. Owner-only (config('agency')
 * lists CRM/email nurture as off-limits to every team seat), so this
 * never needs to worry about which of a team's users a connection belongs
 * to.
 */
class EmailConnectionController extends Controller
{
    protected const SESSION_STATE_KEY = 'gmail_connect_state';

    public function index(): View
    {
        $connection = auth()->user()->emailConnection;
        $gmailAvailable = app(GmailOAuthService::class)->isAvailable();

        return view('dashboard.email-connection.index', compact('connection', 'gmailAvailable'));
    }

    public function redirectToGoogle(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        if (! $gmail->isAvailable()) {
            return back()->with('error', 'Gmail connection is not available right now.');
        }

        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE_KEY, $state);

        return redirect()->away($gmail->authorizationUrl(route('email-connections.gmail.callback'), $state));
    }

    public function handleGoogleCallback(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        $state = $request->session()->pull(self::SESSION_STATE_KEY);

        if (! auth()->check()) {
            return redirect()->route('login')->with('error', 'Please sign in, then try connecting Gmail again.');
        }

        if (! $state || $request->query('state') !== $state || ! $request->query('code')) {
            return redirect()->route('email-connections.index')->with('error', 'Gmail connection failed — please try again.');
        }

        try {
            $result = $gmail->exchangeCode((string) $request->query('code'), route('email-connections.gmail.callback'));
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->route('email-connections.index')->with('error', $e->getMessage());
        }

        EmailConnection::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'provider' => 'gmail',
                'credentials' => [
                    'access_token' => $result['access_token'],
                    'refresh_token' => $result['refresh_token'],
                    'expires_at' => $result['expires_at'],
                ],
                'connected_email' => $result['email'],
                'verified_at' => now(),
                'verification_status' => 'success',
                'verification_message' => "Connected as {$result['email']}.",
            ]
        );

        return redirect()->route('email-connections.index')->with('success', "Gmail connected — CRM nurture emails will now send from {$result['email']}.");
    }

    public function storeSmtp(Request $request, PersonalEmailSender $sender): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $connection = EmailConnection::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'provider' => 'smtp',
                'credentials' => $validated,
                'connected_email' => $validated['from_email'],
                'verified_at' => null,
                'verification_status' => null,
                'verification_message' => null,
            ]
        );

        try {
            $sender->send($connection, auth()->user()->email, 'AffilStack: your SMTP connection works', '<p>This confirms CRM nurture emails can now send through your own SMTP server.</p>');

            $connection->update([
                'verified_at' => now(),
                'verification_status' => 'success',
                'verification_message' => 'Test email sent successfully — check your inbox.',
            ]);

            return redirect()->route('email-connections.index')->with('success', 'SMTP connected — a test email was sent to your account address.');
        } catch (RuntimeException $e) {
            $connection->update([
                'verification_status' => 'failed',
                'verification_message' => $e->getMessage(),
            ]);

            return redirect()->route('email-connections.index')->with('error', "Saved, but the test email failed: {$e->getMessage()}");
        }
    }

    public function disconnect(): RedirectResponse
    {
        auth()->user()->emailConnection?->delete();

        return redirect()->route('email-connections.index')->with('success', 'Disconnected — CRM nurture emails will send from AffilStack\'s own address again.');
    }
}
