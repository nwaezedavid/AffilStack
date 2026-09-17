<div class="space-y-4 text-sm">
    <div>
        <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Method</div>
        <p class="text-gray-600 dark:text-gray-400">{{ config("referrals.payout_methods.{$record->payout_method}", $record->payout_method) }}</p>
    </div>
    <div>
        <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Details</div>
        @if ($record->payout_method === 'paypal')
            <p class="text-gray-600 dark:text-gray-400">PayPal email: {{ $record->payout_details['paypal_email'] ?? '—' }}</p>
        @else
            <ul class="text-gray-600 dark:text-gray-400 space-y-1">
                <li>Account name: {{ $record->payout_details['account_name'] ?? '—' }}</li>
                <li>Account number: {{ $record->payout_details['account_number'] ?? '—' }}</li>
                <li>Bank name: {{ $record->payout_details['bank_name'] ?? '—' }}</li>
                <li>SWIFT / routing code: {{ $record->payout_details['swift_or_routing'] ?? '—' }}</li>
            </ul>
        @endif
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-500">
        These are a snapshot of the affiliate's profile at the moment they requested this payout — a later
        change to their payout profile won't affect where this batch is sent.
    </p>
</div>
