<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Jobs\SyncNetworkConnection;
use App\Models\NetworkConnection;
use App\Services\Networks\Adapters\Digistore24Adapter;
use App\Services\Networks\NetworkConnectionService;
use App\Services\Networks\NetworkException;
use App\Services\Networks\NetworkRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Affiliate Networks: connect Impact, PartnerStack, Awin, CJ, ClickBank
 * and Digistore24 accounts (guided key forms; Digistore24 can approve in
 * the browser), sync on demand and disconnect. Owner-only, like earnings.
 */
class NetworksController extends Controller
{
    public function index(Request $request, NetworkConnectionService $service): View
    {
        $owner = $service->owner($request->user());
        $connections = $owner->networkConnections()->withCount([
            'programs as joined_count' => fn ($query) => $query->where('status', 'joined'),
            'programs as available_count' => fn ($query) => $query->where('status', 'available'),
        ])->get()->keyBy('network');

        return view('dashboard.networks.index', [
            'networks' => collect(NetworkRegistry::keys())->mapWithKeys(fn ($key) => [$key => NetworkRegistry::config($key)]),
            'connections' => $connections,
            'limit' => $service->limitFor($request->user()),
            'used' => $service->usedFor($request->user()),
            'isOwner' => $owner->is($request->user()),
            'earningsCount' => $owner->earnings()->where('source', 'network_sync')->count(),
        ]);
    }

    public function connect(Request $request, string $network, NetworkConnectionService $service): View
    {
        abort_unless(NetworkRegistry::exists($network), 404);
        $this->ensureOwner($request, $service);

        $connection = $service->owner($request->user())->networkConnections()->where('network', $network)->first();

        return view('dashboard.networks.connect', [
            'network' => $network,
            'config' => NetworkRegistry::config($network),
            'connection' => $connection,
            'canApprove' => $network === 'digistore24' && Digistore24Adapter::canApproveInBrowser(),
            'blocked' => $this->blockedReason($request, $network, $service),
        ]);
    }

    public function store(Request $request, string $network, NetworkConnectionService $service): RedirectResponse
    {
        abort_unless(NetworkRegistry::exists($network), 404);
        $this->ensureOwner($request, $service);

        $fields = (array) (NetworkRegistry::config($network)['fields'] ?? []);
        $rules = [];

        foreach ($fields as $name => $field) {
            $rules[$name] = [($field['optional'] ?? false) ? 'nullable' : 'required', 'string', 'max:500'];
        }

        $credentials = array_map(fn ($value) => trim((string) $value), $request->validate($rules));

        try {
            $service->connect($request->user(), $network, array_filter($credentials, fn ($value) => $value !== ''));
        } catch (InvalidArgumentException|NetworkException $e) {
            return back()->withInput($request->except(array_keys(array_filter($fields, fn ($field) => $field['secret'] ?? false))))->with('error', $e->getMessage());
        }

        return redirect()->route('networks.index')->with('success', NetworkRegistry::label($network).' is connected. AffilStack is importing your programs and commissions now; they\'ll appear in a minute or two.');
    }

    /**
     * Digistore24: send the customer to approve AffilStack's access.
     */
    public function digistoreApprove(Request $request, NetworkConnectionService $service, Digistore24Adapter $adapter): RedirectResponse
    {
        $this->ensureOwner($request, $service);

        try {
            $service->ensureCanConnect($request->user(), 'digistore24');
            $approval = $adapter->requestApproval(route('networks.digistore24.return'), route('networks.connect', 'digistore24'));
        } catch (InvalidArgumentException|NetworkException $e) {
            return redirect()->route('networks.connect', 'digistore24')->with('error', $e->getMessage());
        }

        $existing = $service->owner($request->user())->networkConnections()->where('network', 'digistore24')->first();

        if ($existing && $existing->isActive()) {
            // Reconnecting: keep the working key until the new one arrives.
            $existing->update(['connect_token' => $approval['token']]);
        } else {
            NetworkConnection::updateOrCreate(
                ['user_id' => $service->owner($request->user())->id, 'network' => 'digistore24'],
                ['status' => 'pending', 'connect_token' => $approval['token'], 'credentials' => null, 'last_error' => null],
            );
        }

        return redirect()->away($approval['url']);
    }

    /**
     * Digistore24 sends the customer back here after they approve.
     */
    public function digistoreReturn(Request $request, NetworkConnectionService $service, Digistore24Adapter $adapter): RedirectResponse
    {
        $this->ensureOwner($request, $service);

        $connection = $service->owner($request->user())->networkConnections()->where('network', 'digistore24')->first();

        if (! $connection || ! $connection->connect_token) {
            return redirect()->route('networks.connect', 'digistore24')->with('error', 'That approval has expired. Start again.');
        }

        try {
            $key = $adapter->retrieveApprovedKey((string) $connection->connect_token);

            if ($key === null) {
                throw new NetworkException('Digistore24 hasn\'t confirmed the approval. If you cancelled, start again; otherwise paste an API key.');
            }

            $connection->update(['connect_token' => null]);
            $service->connect($request->user(), 'digistore24', ['api_key' => $key]);
        } catch (InvalidArgumentException|NetworkException $e) {
            if ($connection->status === 'pending') {
                $connection->delete();
            } else {
                $connection->update(['connect_token' => null]);
            }

            return redirect()->route('networks.connect', 'digistore24')->with('error', $e->getMessage());
        }

        return redirect()->route('networks.index')->with('success', 'Digistore24 is connected. AffilStack is importing your commissions now.');
    }

    public function sync(Request $request, NetworkConnection $connection, NetworkConnectionService $service): RedirectResponse
    {
        $this->authorizeConnection($request, $connection, $service);

        $cooldown = (int) config('networks.manual_sync_cooldown_minutes', 10);

        if ($connection->last_synced_at && $connection->last_synced_at->gt(now()->subMinutes($cooldown))) {
            return back()->with('error', 'Synced '.$connection->last_synced_at->diffForHumans().'. You can sync again in a few minutes.');
        }

        SyncNetworkConnection::dispatch($connection);

        return back()->with('success', 'Syncing '.$connection->label().' now. New commissions will appear in a minute or two.');
    }

    public function destroy(Request $request, NetworkConnection $connection, NetworkConnectionService $service): RedirectResponse
    {
        $this->authorizeConnection($request, $connection, $service);

        $label = $connection->label();
        $service->disconnect($connection);

        return redirect()->route('networks.index')->with('success', $label.' is disconnected and its keys are deleted. Imported earnings stay; products went back to your own affiliate links.');
    }

    protected function authorizeConnection(Request $request, NetworkConnection $connection, NetworkConnectionService $service): void
    {
        abort_unless($connection->user_id === $service->owner($request->user())->id, 404);
        $this->ensureOwner($request, $service);
    }

    protected function ensureOwner(Request $request, NetworkConnectionService $service): void
    {
        abort_unless($service->owner($request->user())->is($request->user()), 403, 'Only the account owner can manage affiliate network connections.');
    }

    protected function blockedReason(Request $request, string $network, NetworkConnectionService $service): ?string
    {
        try {
            $service->ensureCanConnect($request->user(), $network);

            return null;
        } catch (InvalidArgumentException $e) {
            return Str::limit($e->getMessage(), 300);
        }
    }
}
