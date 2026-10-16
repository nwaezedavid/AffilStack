@extends('developer.docs.layout')

@section('doc')
    <p>You choose: <strong>free</strong>, or a <strong>monthly subscription</strong> in US dollars. AffilStack bills customers — in naira through Paystack for Nigerian customers, or in dollars by card or PayPal — renews each month, handles failed payments and refunds, and pays you.</p>

    <h2>Your share</h2>
    <p>You receive <strong>{{ rtrim(rtrim(number_format($share, 2), '0'), '.') }}%</strong> of every payment, worked out on the dollar price.</p>

    <h2>When you're paid</h2>
    <ul>
        <li>Each payment's share becomes available {{ $holdDays }} days later, after the refund window.</li>
        <li>Request a payout from your Earnings page once you reach the minimum. We review every request, then send it by Nigerian bank transfer (in naira), PayPal or USDT.</li>
        <li>Refunds and chargebacks are taken back from your earnings.</li>
    </ul>

    <h2>Subscriptions</h2>
    <ul>
        <li>Customers can cancel any time and keep the add-on until the end of the month they paid for.</li>
        <li>If a renewal can't be charged, the customer gets {{ config('marketplace.grace_days') }} days to pay before the add-on switches off.</li>
        <li>A price change applies to new subscribers only.</li>
        <li>If you retire an add-on, subscriptions end at the end of their current month.</li>
    </ul>
@endsection
