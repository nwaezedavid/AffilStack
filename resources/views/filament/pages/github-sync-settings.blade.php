<x-filament-panels::page>
    @include('filament.pages._panel-styles')

    <div class="afs-panel-hero">
        <div class="afs-panel-hero-icon">
            <x-filament::icon icon="heroicon-o-code-bracket-square" />
        </div>
        <div>
            <p>
                Connects this platform's own codebase to a GitHub repository via a Personal Access Token, then keeps
                it pushed automatically — daily on a schedule, and any time via "Sync now" above. This only affects
                this application's own tracked source files; no customer data is ever involved.
            </p>
        </div>
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
                Save changes
            </x-filament::button>
        </div>
    </form>

    <h2 class="afs-panel-section-title">Sync history</h2>

    @php($runs = $this->recentRuns())

    @if ($runs->isEmpty())
        <p class="afs-panel-empty">No syncs recorded yet.</p>
    @else
        <table class="afs-panel-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Trigger</th>
                    <th>Status</th>
                    <th>Commit</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td>{{ $run->created_at->diffForHumans() }}</td>
                        <td>{{ ucfirst($run->trigger) }}</td>
                        <td>
                            @if ($run->status === 'success')
                                <x-filament::badge color="success">Success</x-filament::badge>
                            @elseif ($run->status === 'blocked')
                                <x-filament::badge color="warning">Blocked</x-filament::badge>
                            @else
                                <x-filament::badge color="danger">Failed</x-filament::badge>
                            @endif
                        </td>
                        <td>
                            @if ($run->commit_sha)
                                <span class="afs-panel-code">{{ substr($run->commit_sha, 0, 7) }}</span>
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td>{{ $run->message }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-filament-panels::page>
