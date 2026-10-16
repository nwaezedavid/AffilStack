<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\WordpressSite;
use App\Services\WordPress\PluginPackager;
use App\Services\WordPress\WordPressPusher;
use App\Services\WordPress\WordPressSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * WordPress sites: download the AffilStack Connector plugin, approve a
 * site the plugin asks to connect, choose whether new articles are sent
 * automatically, test or disconnect a site.
 */
class WordPressController extends Controller
{
    public function index(Request $request, WordPressSiteService $sites): View
    {
        $user = $request->user();
        $owner = $sites->owner($user);

        return view('dashboard.wordpress.index', [
            'sites' => $owner->wordpressSites()->whereIn('status', ['connected', 'pending'])->withCount(['deliveries as sent_count' => fn ($q) => $q->where('status', 'sent')])->latest()->get(),
            'limit' => $sites->limitFor($user),
            'used' => $sites->usedFor($user),
            'version' => PluginPackager::version(),
            'isOwner' => $owner->is($user),
        ]);
    }

    public function download(Request $request, WordPressSiteService $sites, PluginPackager $packager): BinaryFileResponse|RedirectResponse
    {
        if (! $sites->included($request->user())) {
            return redirect()->route('wordpress.index')->with('error', 'Your plan doesn\'t include the WordPress plugin. Upgrade your plan to use it.');
        }

        try {
            $path = $packager->zipPath();
        } catch (RuntimeException $e) {
            return redirect()->route('wordpress.index')->with('error', $e->getMessage());
        }

        return response()->download($path, PluginPackager::fileName(), ['Content-Type' => 'application/zip']);
    }

    /**
     * The plugin sends the customer here to approve the connection.
     */
    public function connect(Request $request, WordPressSiteService $sites): View
    {
        $error = null;
        $target = null;

        try {
            $target = $sites->validateRequest(
                (string) $request->query('site_url'),
                (string) $request->query('rest_url'),
                (string) $request->query('return_url'),
            );
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }

        if ($error === null && ! preg_match('/^[A-Za-z0-9_-]{16,128}$/', (string) $request->query('state'))) {
            $error = 'The connection request is missing its security check. Start again from the plugin\'s settings page.';
        }

        $limit = $sites->limitFor($request->user());

        return view('dashboard.wordpress.connect', [
            'target' => $target,
            'error' => $error,
            'siteName' => mb_substr(trim(strip_tags((string) $request->query('site_name'))), 0, 120),
            'state' => (string) $request->query('state'),
            'limit' => $limit,
            'used' => $sites->usedFor($request->user()),
            'alreadyConnected' => $target ? $sites->owner($request->user())->wordpressSites()->where('site_url', $target['site_url'])->whereIn('status', ['connected', 'pending'])->exists() : false,
        ]);
    }

    public function approve(Request $request, WordPressSiteService $sites): RedirectResponse|View
    {
        $data = $request->validate([
            'site_url' => ['required', 'string', 'max:255'],
            'rest_url' => ['required', 'string', 'max:255'],
            'return_url' => ['required', 'string', 'max:500'],
            'state' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
            'site_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $target = $sites->validateRequest($data['site_url'], $data['rest_url'], $data['return_url']);
            $code = $sites->startConnection($request->user(), $target['site_url'], $target['rest_url'], $data['site_name'] ?? null);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('wordpress.index')->with('error', $e->getMessage());
        }

        $separator = str_contains($target['return_url'], '?') ? '&' : '?';

        // A page that sends the browser on, rather than a redirect: the
        // site may be on plain http, which the form-action policy would
        // block as the target of a form submission's redirect.
        return view('dashboard.wordpress.return', [
            'returnUrl' => $target['return_url'].$separator.http_build_query([
                'affilstack_code' => $code,
                'affilstack_state' => $data['state'],
            ]),
            'siteHost' => (string) parse_url($target['site_url'], PHP_URL_HOST),
        ]);
    }

    public function update(Request $request, WordpressSite $site, WordPressSiteService $sites): RedirectResponse
    {
        $this->authorizeSite($request, $site, $sites);

        $site->update(['auto_send' => $request->boolean('auto_send')]);

        return back()->with('success', $site->auto_send
            ? 'New blog articles will be sent to '.$site->displayName().' as drafts automatically.'
            : 'New articles will only go to '.$site->displayName().' when you send them.');
    }

    public function test(Request $request, WordpressSite $site, WordPressSiteService $sites, WordPressPusher $pusher): RedirectResponse
    {
        $this->authorizeSite($request, $site, $sites);

        try {
            $pusher->status($site);
        } catch (RuntimeException $e) {
            $site->update(['last_error' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $site->displayName().' is connected and ready.');
    }

    public function destroy(Request $request, WordpressSite $site, WordPressSiteService $sites, WordPressPusher $pusher): RedirectResponse
    {
        $this->authorizeSite($request, $site, $sites);

        if ($site->isConnected()) {
            $pusher->notifyDisconnect($site);
        }

        $sites->disconnect($site);

        return back()->with('success', $site->displayName().' was disconnected. Drafts already on the site stay there.');
    }

    protected function authorizeSite(Request $request, WordpressSite $site, WordPressSiteService $sites): void
    {
        abort_unless($site->user_id === $sites->owner($request->user())->id, 404);
        abort_unless($sites->owner($request->user())->is($request->user()), 403, 'Only the account owner can manage WordPress sites.');
    }
}
