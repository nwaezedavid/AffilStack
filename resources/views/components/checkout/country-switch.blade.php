@props(['isNigeria'])

{{-- Naira (Paystack) is offered only to checkouts detected as Nigerian, and
     only while Paystack is switched on — this is the manual override when
     the detection guesses wrong. Dollar payment is always available. --}}
@if (app(\App\Services\Payments\PaymentGatewayManager::class)->nairaAvailable())
    <form method="POST" action="{{ route('checkout.country.update') }}" {{ $attributes }}>
        @csrf
        <input type="hidden" name="country" value="{{ $isNigeria ? 'US' : 'NG' }}">
        <button type="submit" class="text-xs text-ink-400 hover:text-ink-600 underline">
            {{ $isNigeria ? 'Not in Nigeria? Hide the Naira option' : 'Paying from Nigeria? Show the Naira option' }}
        </button>
    </form>
@endif
