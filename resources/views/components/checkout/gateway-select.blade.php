@props(['gateways', 'compact' => false])

@php($manager = app(\App\Services\Payments\PaymentGatewayManager::class))

@if (count($gateways) > 1)
    <div>
        @unless ($compact)
            <label class="block text-sm font-medium text-ink-900 mb-1">Payment method</label>
        @endunless
        <select name="gateway" aria-label="Payment method" @class([
            'w-full rounded-md border border-line',
            'px-2 py-1.5 text-xs' => $compact,
            'px-3 py-2 text-sm' => ! $compact,
        ])>
            @foreach ($gateways as $gateway)
                <option value="{{ $gateway->key() }}">{{ $manager->optionLabel($gateway) }}</option>
            @endforeach
        </select>
    </div>
@elseif (count($gateways) === 1)
    <input type="hidden" name="gateway" value="{{ $gateways[0]->key() }}">
@endif
