@include('filament.pages._panel-styles')

<div class="afs-panel-detail">
    <div>
        <div class="afs-panel-detail-label">Method</div>
        <p class="afs-panel-detail-value">{{ config("referrals.payout_methods.{$record->payout_method}", $record->payout_method) }}</p>
    </div>
    <div>
        <div class="afs-panel-detail-label">Details</div>
        @if ($record->payout_method === 'paypal')
            <p class="afs-panel-detail-value">PayPal email: {{ $record->payout_details['paypal_email'] ?? '—' }}</p>
        @else
            <ul class="afs-panel-detail-list">
                <li>Account name: {{ $record->payout_details['account_name'] ?? '—' }}</li>
                <li>Account number: {{ $record->payout_details['account_number'] ?? '—' }}</li>
                <li>Bank name: {{ $record->payout_details['bank_name'] ?? '—' }}</li>
                <li>SWIFT / routing code: {{ $record->payout_details['swift_or_routing'] ?? '—' }}</li>
                <li>Flutterwave bank code: {{ $record->payout_details['bank_code'] ?? '— (auto-disbursement unavailable, send manually)' }}</li>
            </ul>
        @endif
    </div>
    <p class="afs-panel-detail-hint">
        These are a snapshot of the affiliate's profile at the moment they requested this payout — a later
        change to their payout profile won't affect where this batch is sent.
    </p>
</div>
