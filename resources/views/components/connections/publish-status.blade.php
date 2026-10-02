@props(['provider', 'connection' => false, 'compact' => false])

{{-- Connection status for one social platform, for wherever content gets
     published or exported to it (e.g. the Publish buttons on a UGC video):
     a status pill plus a "Connect" / "Reconnect" link to Connected Accounts
     when the account is missing or broken.

     <x-connections.publish-status provider="youtube" />
     Pass :connection="$socialConnections->get('youtube')" (null when not
     connected) inside loops to skip the per-component lookup. --}}
@php
    $registry = app(\App\Services\Social\SocialProviderRegistry::class);
    $known = $registry->has($provider);
    $label = $known ? $registry->meta($provider)['label'] : ucfirst($provider);
    $available = $known && $registry->get($provider)->isAvailable();
    if ($connection === false) {
        $connection = auth()->user()?->socialConnections()->where('provider', $provider)->first();
    }
    $status = $connection ? $connection->displayStatus() : 'not_connected';
    $pillLabel = match (true) {
        ! $connection && ! $available => $label.' unavailable',
        ! $connection => $label.' not connected',
        $status === 'verified' => $label.' verified',
        $status === 'expired' => $label.': reconnect',
        $status === 'failed' => $label.': check failed',
        default => $label.' connected',
    };
    $title = $connection?->verification_message
        ?? ($connection ? 'Connected as '.$connection->account_name : ($available ? 'Connect '.$label.' to publish here' : $label.' connections are not available yet'));
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-2']) }} data-publish-status="{{ $provider }}">
    <x-ui.status-pill :status="$status" :label="$compact ? null : $pillLabel" title="{{ $title }}" />
    @if ($available && (! $connection || $connection->isBroken()))
        <a href="{{ route('social-connections.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
            {{ $connection ? 'Reconnect' : 'Connect' }} {{ $compact ? '' : $label }}
            <x-heroicon-m-arrow-right class="h-3 w-3" />
        </a>
    @endif
</span>
