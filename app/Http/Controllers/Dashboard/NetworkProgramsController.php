<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Networks\AddsProgramsByHand;
use App\Services\Networks\NetworkConnectionService;
use App\Services\Networks\NetworkException;
use App\Services\Networks\NetworkLinkService;
use App\Services\Networks\NetworkRegistry;
use App\Services\Networks\NetworkSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Programs on the customer's connected networks: the ones they've joined
 * become AffilStack products in one click (with the network's tracked
 * link); ones they haven't joined link to the network to apply. ClickBank
 * and Digistore24 have no marketplace API, so products are added there by
 * vendor nickname / product ID.
 */
class NetworkProgramsController extends Controller
{
    public function index(Request $request, NetworkConnectionService $service): View
    {
        $owner = $service->owner($request->user());
        $connections = $owner->networkConnections()->active()->get()->keyBy('id');
        $status = in_array($request->query('status'), ['joined', 'pending', 'available'], true) ? $request->query('status') : 'joined';
        $network = $connections->firstWhere('network', $request->query('network'));
        $search = trim((string) $request->query('q'));

        $programs = NetworkProgram::query()
            ->with(['connection', 'offers' => fn ($query) => $query->select('id', 'network_program_id', 'product_name')])
            ->whereIn('network_connection_id', $network ? [$network->id] : $connections->keys())
            ->where('status', $status)
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('advertiser_name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('category', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $counts = NetworkProgram::query()
            ->whereIn('network_connection_id', $network ? [$network->id] : $connections->keys())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard.networks.programs', [
            'programs' => $programs,
            'connections' => $connections,
            'network' => $network,
            'status' => $status,
            'search' => $search,
            'counts' => $counts,
            'manualConnections' => $connections->filter(fn (NetworkConnection $connection) => NetworkRegistry::adapter($connection->network) instanceof AddsProgramsByHand),
            'isOwner' => $owner->is($request->user()),
        ]);
    }

    public function import(Request $request, NetworkProgram $program, NetworkConnectionService $service, NetworkLinkService $links): RedirectResponse
    {
        $this->authorizeProgram($request, $program, $service);

        try {
            $offer = $links->importProgram($request->user(), $program);
        } catch (InsufficientCreditsException) {
            return back()->with('error', 'Not enough credits for offer research. Upgrade your plan or buy a credit top-up.');
        } catch (InvalidArgumentException|NetworkException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('offers.show', $offer)->with('success', $program->name.' was added with your '.$program->connection->label().' tracked link. Research is running; we\'ll notify you when it\'s ready.');
    }

    public function addByHand(Request $request, NetworkConnection $connection, NetworkConnectionService $service, NetworkSyncService $sync): RedirectResponse
    {
        abort_unless($connection->user_id === $service->owner($request->user())->id && $connection->isActive(), 404);
        abort_unless($service->owner($request->user())->is($request->user()), 403);

        $adapter = NetworkRegistry::adapter($connection->network);
        abort_unless($adapter instanceof AddsProgramsByHand, 404);

        $data = $request->validate(['identifier' => ['required', 'string', 'max:300']]);

        try {
            $program = $sync->saveProgram($connection, $adapter->programByHand($connection, $data['identifier']), manual: true);
        } catch (NetworkException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('networks.programs', ['network' => $connection->network, 'status' => $program->status])
            ->with('success', $program->isJoined()
                ? $program->name.' is ready. Click "Add as product" to start promoting it.'
                : $program->name.' needs approval on '.$connection->label().' first. It\'s listed under "'.($program->status === 'pending' ? 'Waiting for approval' : 'Not joined').'".');
    }

    protected function authorizeProgram(Request $request, NetworkProgram $program, NetworkConnectionService $service): void
    {
        abort_unless($program->connection && $program->connection->user_id === $service->owner($request->user())->id, 404);
        abort_unless($service->owner($request->user())->is($request->user()), 403);
    }
}
