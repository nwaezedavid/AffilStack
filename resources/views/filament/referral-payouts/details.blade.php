@include('filament.pages._panel-styles')

@php
    $details = $record->payout_details ?? [];
    $network = config('referrals.usdt_networks.'.($details['network'] ?? ''));
@endphp
<div class="afs-panel-detail">
    <div>
        <div class="afs-panel-detail-label">Method</div>
        <p class="afs-panel-detail-value">{{ \App\Support\ReferralProgram::methodLabel($record->payout_method) }}</p>
    </div>
    <div>
        <div class="afs-panel-detail-label">Amount</div>
        <p class="afs-panel-detail-value">{{ \App\Support\ReferralProgram::money($record->amount_cents, $record->currency) }}</p>
    </div>
    <div>
        <div class="afs-panel-detail-label">Details</div>
        @switch($record->payout_method)
            @case('paypal')
                <p class="afs-panel-detail-value">PayPal email: {{ $details['paypal_email'] ?? '—' }}</p>
                @break
            @case('paystack_bank')
                <ul class="afs-panel-detail-list">
                    <li>Account name: {{ $details['account_name'] ?? '—' }} @if (! empty($details['verified']))(checked with the bank)@endif</li>
                    <li>Account number: {{ $details['account_number'] ?? '—' }}</li>
                    <li>Bank: {{ $details['bank_name'] ?? '—' }} ({{ $details['bank_code'] ?? '—' }})</li>
                </ul>
                @break
            @case('usdt')
                <ul class="afs-panel-detail-list">
                    <li>Network: {{ $network['label'] ?? strtoupper($details['network'] ?? '—') }}</li>
                    <li>Wallet address: <code style="word-break: break-all">{{ $details['address'] ?? '—' }}</code></li>
                    @if ($record->reference && $network)
                        <li>Transaction: <a href="{{ $network['explorer'].$record->reference }}" target="_blank" rel="noopener" style="text-decoration: underline">view on block explorer</a></li>
                    @endif
                </ul>
                @break
            @default
                <ul class="afs-panel-detail-list">
                    <li>Account name: {{ $details['account_name'] ?? '—' }}</li>
                    <li>Account number: {{ $details['account_number'] ?? '—' }}</li>
                    <li>Bank name: {{ $details['bank_name'] ?? '—' }}</li>
                    <li>SWIFT / routing code: {{ $details['swift_or_routing'] ?? '—' }}</li>
                </ul>
        @endswitch
    </div>
    @if ($record->note)
        <div>
            <div class="afs-panel-detail-label">Note</div>
            <p class="afs-panel-detail-value">{{ $record->note }}</p>
        </div>
    @endif
    <p class="afs-panel-detail-hint">
        A snapshot of the partner's payout details when they requested this payout. Changing their details later doesn't affect where this one goes.
    </p>
</div>
