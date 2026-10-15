{{--
    Where to send commission — shared by the Partner Portal and the customer
    Refer & Earn page. Needs $user, $banks (code => name from Paystack),
    $action (form URL) and optional $resolveUrl (live account-name lookup).
--}}
@php
    $details = $user->payout_details ?? [];
    $bankAvailable = $banks !== [];
    $currentMethod = old('payout_method', array_key_exists((string) $user->payout_method, config('referrals.payout_methods')) ? $user->payout_method : ($bankAvailable ? 'paystack_bank' : 'paypal'));
    $field = 'w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600';
    $methodInfo = [
        'paystack_bank' => ['icon' => 'building-library', 'hint' => 'Naira, sent by Paystack'],
        'paypal' => ['icon' => 'globe-alt', 'hint' => 'Any PayPal account'],
        'usdt' => ['icon' => 'currency-dollar', 'hint' => 'TRC-20 or BEP-20'],
    ];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4" data-payout-form @if (! empty($resolveUrl)) data-resolve-url="{{ $resolveUrl }}" @endif>
    @csrf
    <fieldset>
        <legend class="mb-2 block text-xs font-medium text-ink-900">Get paid by</legend>
        <div class="grid gap-2 sm:grid-cols-3">
            @foreach (config('referrals.payout_methods') as $value => $label)
                <label class="relative flex cursor-pointer flex-col gap-1 rounded-xl border border-line bg-white p-3 text-sm transition has-[:checked]:border-emerald-600 has-[:checked]:ring-1 has-[:checked]:ring-emerald-600 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50">
                    <input type="radio" name="payout_method" value="{{ $value }}" class="sr-only" data-payout-method @checked($currentMethod === $value) @disabled($value === 'paystack_bank' && ! $bankAvailable)>
                    <x-dynamic-component :component="'heroicon-o-'.$methodInfo[$value]['icon']" class="h-5 w-5 text-emerald-700" />
                    <span class="font-medium text-navy-900">{{ $label }}</span>
                    <span class="text-xs text-ink-400">{{ $value === 'paystack_bank' && ! $bankAvailable ? 'Not available yet' : $methodInfo[$value]['hint'] }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div data-payout-fields="paystack_bank" class="space-y-3 {{ $currentMethod === 'paystack_bank' ? '' : 'hidden' }}">
        <div>
            <label for="bank_code" class="mb-1 block text-xs font-medium text-ink-900">Bank</label>
            <select id="bank_code" name="bank_code" class="{{ $field }}" data-bank-code>
                <option value="">Choose your bank</option>
                @foreach ($banks as $code => $name)
                    <option value="{{ $code }}" @selected(old('bank_code', $details['bank_code'] ?? '') === (string) $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="account_number" class="mb-1 block text-xs font-medium text-ink-900">Account number</label>
            <input id="account_number" name="account_number" inputmode="numeric" maxlength="10" autocomplete="off" value="{{ old('account_number', $details['account_number'] ?? '') }}" placeholder="10-digit NUBAN" class="{{ $field }} font-mono" data-account-number>
            <p class="mt-1.5 min-h-5 text-xs" data-account-name aria-live="polite">
                @if (($user->payout_method === 'paystack_bank') && filled($details['account_name'] ?? null))
                    <span class="text-emerald-700">✓ {{ $details['account_name'] }}</span>
                @else
                    <span class="text-ink-400">We check the name with your bank before saving.</span>
                @endif
            </p>
        </div>
    </div>

    <div data-payout-fields="paypal" class="{{ $currentMethod === 'paypal' ? '' : 'hidden' }}">
        <label for="paypal_email" class="mb-1 block text-xs font-medium text-ink-900">PayPal email</label>
        <input id="paypal_email" type="email" name="paypal_email" value="{{ old('paypal_email', $details['paypal_email'] ?? '') }}" autocomplete="email" class="{{ $field }}">
    </div>

    <div data-payout-fields="usdt" class="space-y-3 {{ $currentMethod === 'usdt' ? '' : 'hidden' }}">
        <div>
            <label for="usdt_network" class="mb-1 block text-xs font-medium text-ink-900">Network</label>
            <select id="usdt_network" name="usdt_network" class="{{ $field }}">
                @foreach (config('referrals.usdt_networks') as $value => $network)
                    <option value="{{ $value }}" @selected(old('usdt_network', $details['network'] ?? 'trc20') === $value)>{{ $network['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="usdt_address" class="mb-1 block text-xs font-medium text-ink-900">USDT wallet address</label>
            <input id="usdt_address" name="usdt_address" autocomplete="off" spellcheck="false" value="{{ old('usdt_address', $details['address'] ?? '') }}" class="{{ $field }} font-mono text-xs">
            <p class="mt-1.5 text-xs text-amber-700">Double-check the network. USDT sent to an address on the wrong network can't be recovered.</p>
        </div>
    </div>

    <button type="submit" class="w-full rounded-full bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Save payout details</button>
</form>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-payout-form]').forEach(function (form) {
                    function sync() {
                        var chosen = form.querySelector('[data-payout-method]:checked');
                        form.querySelectorAll('[data-payout-fields]').forEach(function (group) {
                            group.classList.toggle('hidden', !chosen || group.getAttribute('data-payout-fields') !== chosen.value);
                        });
                    }
                    form.querySelectorAll('[data-payout-method]').forEach(function (radio) { radio.addEventListener('change', sync); });

                    var url = form.getAttribute('data-resolve-url');
                    var bank = form.querySelector('[data-bank-code]');
                    var number = form.querySelector('[data-account-number]');
                    var out = form.querySelector('[data-account-name]');
                    var token = form.querySelector('input[name=_token]').value;
                    var timer = null;

                    function say(text, tone) {
                        out.textContent = '';
                        var span = document.createElement('span');
                        span.className = tone === 'ok' ? 'text-emerald-700' : (tone === 'bad' ? 'text-rose-600' : 'text-ink-400');
                        span.textContent = text;
                        out.appendChild(span);
                    }

                    function lookup() {
                        if (!url || !bank.value || !/^\d{10}$/.test(number.value)) { return; }
                        say('Checking with your bank…');
                        fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ bank_code: bank.value, account_number: number.value })
                        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                          .then(function (res) {
                              if (res.ok && res.data.account_name) { say('✓ ' + res.data.account_name, 'ok'); }
                              else { say((res.data && res.data.message) || 'We couldn\'t find that account.', 'bad'); }
                          })
                          .catch(function () { say('We couldn\'t check the account just now. You can still save; we\'ll check again.', 'bad'); });
                    }

                    if (bank && number) {
                        number.addEventListener('input', function () {
                            number.value = number.value.replace(/\D/g, '').slice(0, 10);
                            clearTimeout(timer);
                            timer = setTimeout(lookup, 350);
                        });
                        bank.addEventListener('change', lookup);
                    }
                });
            })();
        </script>
    @endpush
@endonce
