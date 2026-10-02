<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\EmailConnection;
use App\Models\GoogleOauthSetting;
use App\Services\Crm\ConnectionVerifier;
use App\Services\Crm\DomainAuthCheck;
use App\Services\Crm\GmailOAuthService;
use App\Services\Crm\SendingQuota;
use App\Support\OutboundUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Email Sending: connect the user's own Gmail (OAuth) or any SMTP server
 * for CRM campaigns/nurture, plus an IMAP mailbox for reply detection.
 * Every save is verified live (real SMTP AUTH / IMAP LOGIN — see
 * ConnectionVerifier) and the result drives the page's status pills.
 * Owner-only (config('agency') keeps CRM off-limits to team seats).
 * Credentials are encrypted at rest (EmailConnection casts).
 */
class EmailConnectionController extends Controller
{
    protected const SESSION_STATE_KEY = 'gmail_connect_state';

    public function index(SendingQuota $quota): View
    {
        $user = auth()->user();
        $connection = $user->emailConnection;
        $gmailAvailable = app(GmailOAuthService::class)->isAvailable();

        return view('dashboard.email-connection.index', [
            'connection' => $connection,
            'gmailAvailable' => $gmailAvailable,
            'gmailReadingAvailable' => GoogleOauthSetting::current()->gmailReadingAvailable(),
            'presets' => config('crm.presets'),
            'sentToday' => $quota->sentToday($user),
            'dailyCap' => $quota->cap($user),
            'providerCaps' => config('crm.daily_caps'),
            'senderDomain' => $connection ? Str::after((string) $connection->connected_email, '@') : null,
            'defaultBusinessName' => $user->company_name ?: $user->name,
            'defaultAddress' => implode(', ', array_filter([$user->city, $user->state_region, $user->country])),
            'dns' => $connection ? app(DomainAuthCheck::class)->check((string) $connection->connected_email) : null,
        ]);
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

        $existing = auth()->user()->emailConnection;

        EmailConnection::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'provider' => 'gmail',
                'preset' => 'gmail',
                'credentials' => [
                    'access_token' => $result['access_token'],
                    'refresh_token' => $result['refresh_token'],
                    'expires_at' => $result['expires_at'],
                    'from_name' => $existing?->credential('from_name') ?: auth()->user()->name,
                ],
                'connected_email' => $result['email'],
                'verified_at' => now(),
                'last_checked_at' => now(),
                'verification_status' => 'success',
                'verification_message' => "Connected as {$result['email']}.",
                'gmail_read_granted' => (bool) ($result['read_granted'] ?? false),
                'gmail_history_id' => null,
            ]
        );

        return redirect()->route('email-connections.index')->with('success', "Gmail connected — CRM emails will now send from {$result['email']}.");
    }

    public function storeSmtp(Request $request, ConnectionVerifier $verifier): RedirectResponse
    {
        $existing = auth()->user()->emailConnection;
        $keepPassword = $existing?->isSmtp() && blank($request->input('password'));

        $validated = $request->validate([
            'preset' => ['nullable', Rule::in(array_keys(config('crm.presets')))],
            // A real outgoing-mail server on the public internet: without
            // this the connection test could be pointed at the platform's own
            // private network to probe ports and read service banners.
            'host' => ['required', 'string', 'max:255', $this->publicHostRule()],
            'port' => ['required', 'integer', Rule::in([25, 465, 587, 2525])],
            'encryption' => ['nullable', Rule::in(['ssl', 'tls'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$keepPassword ? 'nullable' : 'required', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'send_test' => ['nullable', 'boolean'],
        ]);

        $credentials = collect($validated)->only(['host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name'])->all();
        $credentials['encryption'] ??= (int) $validated['port'] === 465 ? 'ssl' : 'tls';

        if ($keepPassword) {
            $credentials['password'] = $existing->credential('password');
        }

        $connection = EmailConnection::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'provider' => 'smtp',
                'preset' => $validated['preset'] ?? 'custom',
                'credentials' => $credentials,
                'connected_email' => $validated['from_email'],
                'verified_at' => null,
                'verification_status' => null,
                'verification_message' => null,
                'gmail_read_granted' => false,
            ]
        );

        $sendTest = $request->has('send_test') ? $request->boolean('send_test') : true;

        if ($verifier->verifySending($connection, $sendTest ? auth()->user()->email : null)) {
            return redirect()->route('email-connections.index')->with('success', 'SMTP verified — your campaigns will send from '.$connection->connected_email.($sendTest ? '. A test email is on its way to '.auth()->user()->email.'.' : '.'));
        }

        return redirect()->route('email-connections.index')->with('error', 'Saved, but the connection test failed: '.$connection->fresh()->verification_message);
    }

    public function storeImap(Request $request, ConnectionVerifier $verifier): RedirectResponse
    {
        $connection = auth()->user()->emailConnection;

        if (! $connection) {
            return back()->with('error', 'Connect Gmail or SMTP for sending first, then add reply detection.');
        }

        $sameAsSmtp = $request->boolean('same_as_smtp') && $connection->isSmtp();
        $keepPassword = $connection->hasImap() && blank($request->input('password'));

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255', $this->publicHostRule()],
            'port' => ['required', 'integer', Rule::in([993, 143])],
            'encryption' => ['required', Rule::in(['ssl', 'tls'])],
            'username' => [$sameAsSmtp ? 'nullable' : 'required', 'string', 'max:255'],
            'password' => [$sameAsSmtp || $keepPassword ? 'nullable' : 'required', 'string', 'max:255'],
        ]);

        $credentials = [
            'host' => $validated['host'],
            'port' => (int) $validated['port'],
            'encryption' => $validated['encryption'],
            'username' => $sameAsSmtp ? $connection->credential('username') : $validated['username'],
            'password' => $sameAsSmtp ? $connection->credential('password') : ($validated['password'] ?: $connection->imapCredential('password')),
        ];

        $hostChanged = $connection->imapCredential('host') !== $credentials['host'] || $connection->imapCredential('username') !== $credentials['username'];

        $connection->update([
            'imap_credentials' => $credentials,
            'imap_status' => null,
            'imap_message' => null,
            'imap_uidvalidity' => $hostChanged ? null : $connection->imap_uidvalidity,
            'imap_last_uid' => $hostChanged ? null : $connection->imap_last_uid,
        ]);

        if ($verifier->verifyImap($connection)) {
            return redirect()->route('email-connections.index')->with('success', 'Reply detection is on — we\'ll check '.$credentials['username'].' for replies every 5 minutes.');
        }

        return redirect()->route('email-connections.index')->with('error', 'Saved, but we couldn\'t open the inbox: '.$connection->fresh()->imap_message);
    }

    public function destroyImap(): RedirectResponse
    {
        auth()->user()->emailConnection?->update([
            'imap_credentials' => null, 'imap_status' => null, 'imap_message' => null, 'imap_verified_at' => null,
            'imap_uidvalidity' => null, 'imap_last_uid' => null, 'imap_last_polled_at' => null,
        ]);

        return redirect()->route('email-connections.index')->with('success', 'Reply detection turned off — your mailbox password was deleted.');
    }

    /**
     * "Test connection": re-runs every live check now.
     */
    public function test(Request $request, ConnectionVerifier $verifier): RedirectResponse
    {
        $connection = auth()->user()->emailConnection;

        if (! $connection) {
            return back()->with('error', 'Nothing to test yet — connect Gmail or SMTP first.');
        }

        $sendingOk = $verifier->verifySending($connection, $request->boolean('send_test') ? auth()->user()->email : null);
        $imapOk = $connection->hasImap() ? $verifier->verifyImap($connection) : null;

        if ($sendingOk && $imapOk !== false) {
            return back()->with('success', 'All checks passed — '.($imapOk ? 'sending and reply detection are working.' : 'sending is working.'));
        }

        return back()->with('error', 'Some checks failed — see the red status below for what to fix.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $connection = auth()->user()->emailConnection;

        if (! $connection) {
            return back()->with('error', 'Connect Gmail or SMTP first.');
        }

        $ceiling = (int) config('crm.daily_caps.'.($connection->isGmail() ? 'gmail' : 'smtp'));

        $validated = $request->validate([
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:'.$ceiling],
            'from_name' => ['nullable', 'string', 'max:255'],
            'footer_business_name' => ['nullable', 'string', 'max:255'],
            'footer_address' => ['nullable', 'string', 'max:500'],
        ], ['daily_limit.max' => "The most this provider allows is {$ceiling} a day."]);

        $connection->update([
            'daily_limit' => $validated['daily_limit'] ?? null,
            'footer_business_name' => $validated['footer_business_name'] ?? null,
            'footer_address' => $validated['footer_address'] ?? null,
            'credentials' => [...$connection->credentials ?? [], 'from_name' => $validated['from_name'] ?? null],
        ]);

        return back()->with('success', 'Sending settings saved.');
    }

    public function disconnect(): RedirectResponse
    {
        $connection = auth()->user()->emailConnection;

        // Revoke at Google too — deleting our copy alone left a refresh token
        // with send-mail rights valid at Google indefinitely.
        if ($connection?->provider === 'gmail') {
            app(GmailOAuthService::class)->revoke((string) ($connection->credential('refresh_token') ?: $connection->credential('access_token')));
        }

        $connection?->delete();

        return redirect()->route('email-connections.index')->with('success', 'Disconnected — campaigns can\'t send until you connect an email again.');
    }

    protected function publicHostRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            try {
                OutboundUrlGuard::resolveSafeHost((string) $value);
            } catch (\InvalidArgumentException $e) {
                $fail($e->getMessage());
            }
        };
    }
}
