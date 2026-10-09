@extends('layouts.app')

@section('title', 'Profile & Security')

@php
    $input = 'block w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-[0_1px_1px_rgba(16,24,40,0.04)] placeholder:text-ink-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/25';
    $inputError = 'border-red-400 focus:border-red-500 focus:ring-red-500/25';

    $personalErrors = $errors->getBag('personal');
    $businessErrors = $errors->getBag('business');
    $photoErrors = $errors->getBag('photo');
    $passwordErrors = $errors->getBag('updatePassword');

    // Open whichever tab has an error on it, so a failed save is never hidden behind another tab.
    $activeTab = match (true) {
        $personalErrors->any() || $photoErrors->any() => 'personal',
        $businessErrors->any() => 'business',
        $passwordErrors->any() || $errors->getBag('confirmTwoFactorAuthentication')->any() || $errors->has('password') => 'security',
        default => $tab,
    };

    $tabs = [
        'personal' => ['label' => 'Personal', 'icon' => 'user'],
        'business' => ['label' => 'Business', 'icon' => 'briefcase'],
        'security' => ['label' => 'Security', 'icon' => 'shield-check'],
        'notifications' => ['label' => 'Notifications', 'icon' => 'bell'],
    ];

    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(2000, $m, 1)->format('F')]);
    $plan = $user->billableUser()->activeSubscription?->plan?->name;
    $fortifyStatus = [
        'password-updated' => 'Your password was changed.',
        'two-factor-authentication-enabled' => 'Scan the QR code below to finish turning on two-factor authentication.',
        'two-factor-authentication-confirmed' => 'Two-factor authentication is on.',
        'two-factor-authentication-disabled' => 'Two-factor authentication was turned off.',
        'recovery-codes-generated' => 'New recovery codes were generated — store them somewhere safe.',
    ][session('status')] ?? null;
    $industries = ['Affiliate marketing', 'Blogging & content', 'Coaching & consulting', 'E-commerce', 'Education & courses', 'Finance & investing', 'Health & fitness', 'Marketing agency', 'Real estate', 'SaaS & software', 'Travel', 'Beauty & fashion', 'Crypto & Web3', 'Personal development'];
@endphp

@section('content')
    <x-ui.page-header
        title="Profile & Security"
        eyebrow="Your account"
        icon="user-circle"
        subtitle="Your personal details, business info, sign-in security and email preferences, all in one place." />

    {{-- Summary: photo, identity, completeness --}}
    <div class="grid gap-5 lg:grid-cols-3 mb-6">
        <x-ui.card class="lg:col-span-2" padding="p-5 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <div class="relative shrink-0 self-start">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="Your profile photo" class="h-24 w-24 rounded-2xl object-cover ring-4 ring-brand-50">
                    @else
                        <span class="inline-flex h-24 w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 via-navy-800 to-navy-950 font-display text-3xl font-semibold text-white ring-4 ring-brand-50" aria-hidden="true">{{ $user->initials() }}</span>
                    @endif
                    <label for="photo-input" class="absolute -bottom-2 -right-2 inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-gold-400 text-navy-950 shadow ring-4 ring-white transition hover:bg-gold-300 focus-within:ring-brand-500" title="Change photo">
                        <x-heroicon-s-camera class="h-4 w-4" />
                        <span class="sr-only">Change profile photo</span>
                    </label>
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="font-display text-xl font-semibold text-navy-900 truncate">{{ $user->name }}</h3>
                    <p class="text-sm text-ink-600 truncate">{{ $user->email }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @if ($user->company_name)
                            <span class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2.5 py-1 text-ink-600"><x-heroicon-m-building-storefront class="h-3.5 w-3.5" /> {{ $user->company_name }}</span>
                        @endif
                        @if ($country)
                            <span class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2.5 py-1 text-ink-600"><x-heroicon-m-map-pin class="h-3.5 w-3.5" /> {{ collect([$user->city, $countries[$country] ?? null])->filter()->implode(', ') }}</span>
                        @endif
                        @if ($plan)
                            <span class="inline-flex items-center gap-1 rounded-full bg-gold-300/25 px-2.5 py-1 font-medium text-gold-600"><x-heroicon-m-sparkles class="h-3.5 w-3.5" /> {{ $plan }} plan</span>
                        @endif
                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2.5 py-1 text-ink-600"><x-heroicon-m-calendar-days class="h-3.5 w-3.5" /> Member since {{ $user->created_at?->format('M Y') }}</span>
                    </div>

                    <form id="photo-form" method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-2">
                        @csrf
                        <input id="photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" aria-describedby="photo-help">
                        <label for="photo-input" class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line transition hover:bg-surface-muted">
                            <x-heroicon-m-arrow-up-tray class="h-4 w-4" /> {{ $user->avatar_path ? 'Change photo' : 'Upload photo' }}
                        </label>
                        <noscript><x-ui.button size="sm">Save photo</x-ui.button></noscript>
                        <span id="photo-help" class="text-xs text-ink-400">JPG, PNG or WebP, up to 5 MB. We'll crop it to a square.</span>
                    </form>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.photo.destroy') }}" class="mt-1">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-ink-400 underline-offset-2 hover:text-red-600 hover:underline">Remove photo</button>
                        </form>
                    @endif
                    @if ($photoErrors->has('photo'))
                        <p class="mt-2 flex items-center gap-1 text-xs text-red-600"><x-heroicon-m-exclamation-circle class="h-4 w-4" /> {{ $photoErrors->first('photo') }}</p>
                    @endif
                </div>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-navy-900">Profile strength</p>
                <span class="font-display text-2xl font-semibold tabular-nums {{ $completeness['percent'] === 100 ? 'text-emerald-600' : 'text-navy-900' }}">{{ $completeness['percent'] }}%</span>
            </div>
            <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $completeness['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completeness">
                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-gold-400" style="width: {{ $completeness['percent'] }}%"></div>
            </div>
            @if ($completeness['missing'])
                <ul class="mt-4 space-y-2">
                    @foreach (array_slice($completeness['missing'], 0, 3) as $item)
                        <li>
                            <a href="{{ route('profile', ['tab' => $item['tab']]) }}" data-tab-link="{{ $item['tab'] }}" class="flex items-center gap-2 text-sm text-ink-600 hover:text-brand-600">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-dashed border-ink-400"></span>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 flex items-center gap-2 text-sm text-emerald-700"><x-heroicon-s-check-badge class="h-5 w-5" /> Your profile is complete. Nice work!</p>
            @endif
        </x-ui.card>
    </div>

    {{-- Tabs --}}
    <div class="mb-5">
        <div role="tablist" aria-label="Profile sections" class="grid grid-cols-2 gap-1 rounded-2xl border border-line bg-surface p-1 shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:inline-flex">
            @foreach ($tabs as $key => $meta)
                <button type="button" role="tab" id="tab-{{ $key }}" data-tab="{{ $key }}"
                        aria-controls="panel-{{ $key }}" aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}" tabindex="{{ $activeTab === $key ? '0' : '-1' }}"
                        class="profile-tab flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $activeTab === $key ? 'bg-navy-900 text-white shadow-sm' : 'text-ink-600 hover:bg-surface-muted hover:text-navy-900' }}">
                    <x-dynamic-component :component="'heroicon-o-'.$meta['icon']" class="h-4 w-4" />
                    {{ $meta['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ───────────────────────── Personal ───────────────────────── --}}
    <section role="tabpanel" id="panel-personal" aria-labelledby="tab-personal" data-panel="personal" @class(['hidden' => $activeTab !== 'personal'])>
        <form method="POST" action="{{ route('profile.personal.update') }}" class="space-y-5" novalidate>
            @csrf @method('PUT')

            <x-ui.card title="About you" description="How you appear across your dashboard and in emails from us." icon="identification">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Full name" for="name" :error="$personalErrors->first('name')">
                        <input id="name" name="name" type="text" autocomplete="name" required value="{{ old('name', $user->name) }}" class="{{ $input }} {{ $personalErrors->has('name') ? $inputError : '' }}">
                    </x-ui.field>
                    <x-ui.field label="Email address" for="email" :error="$personalErrors->first('email')" hint="We send receipts and sign-in links here.">
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email', $user->email) }}" class="{{ $input }} {{ $personalErrors->has('email') ? $inputError : '' }}">
                    </x-ui.field>
                    @unless ($user->google_id)
                        <x-ui.field label="Current password" for="current_password" :error="$personalErrors->first('current_password')" hint="Only needed when you change your email address.">
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="{{ $input }} {{ $personalErrors->has('current_password') ? $inputError : '' }}">
                        </x-ui.field>
                    @endunless
                </div>
            </x-ui.card>

            <x-ui.card title="Your birthday" description="We'd love to celebrate with you. Expect a little surprise in your inbox on the day." icon="cake">
                <fieldset>
                    <legend class="sr-only">Birthday</legend>
                    <div class="grid gap-4 grid-cols-2 sm:grid-cols-3">
                        <x-ui.field label="Day" for="birth_day" :error="$personalErrors->first('birth_day')">
                            <select id="birth_day" name="birth_day" class="{{ $input }} {{ $personalErrors->has('birth_day') ? $inputError : '' }}">
                                <option value="">Day</option>
                                @foreach (range(1, 31) as $d)
                                    <option value="{{ $d }}" @selected((int) old('birth_day', $birthDay) === $d)>{{ $d }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Month" for="birth_month" :error="$personalErrors->first('birth_month')">
                            <select id="birth_month" name="birth_month" class="{{ $input }} {{ $personalErrors->has('birth_month') ? $inputError : '' }}">
                                <option value="">Month</option>
                                @foreach ($months as $number => $monthName)
                                    <option value="{{ $number }}" @selected((int) old('birth_month', $birthMonth) === $number)>{{ $monthName }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Year" for="birth_year" optional class="col-span-2 sm:col-span-1" :error="$personalErrors->first('birth_year')">
                            <select id="birth_year" name="birth_year" class="{{ $input }} {{ $personalErrors->has('birth_year') ? $inputError : '' }}">
                                <option value="">Rather not say</option>
                                @foreach (range(now()->year - 13, 1920) as $y)
                                    <option value="{{ $y }}" @selected((int) old('birth_year', $birthYear) === $y)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                    <p class="mt-3 flex items-start gap-2 text-xs text-ink-400">
                        <x-heroicon-m-lock-closed class="h-4 w-4 shrink-0" />
                        Day and month are all we need. The year is optional, never shown to anyone and never used to work out your age.
                    </p>
                </fieldset>
            </x-ui.card>

            <x-ui.card title="Contact & identity" description="Optional details that help us personalise your experience." icon="phone">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Phone number" for="phone_number" optional class="sm:col-span-2" :error="$personalErrors->first('phone_number') ?: $personalErrors->first('phone_country')" hint="Include your country code so we can reach you if there's ever a problem with your account.">
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label for="phone_country" class="sr-only">Country code</label>
                            <select id="phone_country" name="phone_country" class="{{ $input }} sm:max-w-[16rem]">
                                @foreach ($dialCodes as $code => $label)
                                    <option value="{{ $code }}" @selected(old('phone_country', $phoneCountry) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input id="phone_number" name="phone_number" type="tel" inputmode="tel" autocomplete="tel-national" placeholder="803 123 4567" value="{{ old('phone_number', $phoneNumber) }}" class="{{ $input }} {{ $personalErrors->has('phone_number') ? $inputError : '' }}">
                        </div>
                    </x-ui.field>

                    <x-ui.field label="Gender" for="gender" optional :error="$personalErrors->first('gender')">
                        <select id="gender" name="gender" class="{{ $input }}">
                            <option value="">Choose…</option>
                            @foreach (\App\Support\Lifecycle\LifecycleAudience::GENDERS as $value => $label)
                                <option value="{{ $value }}" @selected(old('gender', $user->gender) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Religion" for="religion" optional :error="$personalErrors->first('religion')">
                        <select id="religion" name="religion" class="{{ $input }}" aria-describedby="religion-privacy">
                            <option value="">Choose…</option>
                            @foreach (\App\Support\Lifecycle\LifecycleAudience::RELIGIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('religion', $user->religion) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>

                <div id="religion-privacy" class="mt-5 flex items-start gap-3 rounded-xl bg-emerald-50/70 p-4 ring-1 ring-emerald-100">
                    <x-heroicon-o-shield-check class="h-5 w-5 shrink-0 text-emerald-600" />
                    <p class="text-xs text-emerald-900">
                        <span class="font-semibold">Your privacy matters.</span>
                        Your religion is only used to send you the right festive greetings (say, Eid Mubarak rather than Merry Christmas). It's never shared with anyone, never shown on your profile, and you can clear it at any time.
                    </p>
                </div>
            </x-ui.card>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <x-ui.button variant="brand" icon="check">Save personal details</x-ui.button>
            </div>
        </form>
    </section>

    {{-- ───────────────────────── Business ───────────────────────── --}}
    <section role="tabpanel" id="panel-business" aria-labelledby="tab-business" data-panel="business" @class(['hidden' => $activeTab !== 'business'])>
        <form method="POST" action="{{ route('profile.business.update') }}" class="space-y-5" novalidate>
            @csrf @method('PUT')

            <x-ui.card title="Your business" description="Used to personalise your content, emails and greetings." icon="building-storefront">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Business name" for="company_name" optional :error="$businessErrors->first('company_name')">
                        <input id="company_name" name="company_name" type="text" autocomplete="organization" placeholder="e.g. Prime Digital Hub" value="{{ old('company_name', $user->company_name) }}" class="{{ $input }} {{ $businessErrors->has('company_name') ? $inputError : '' }}">
                    </x-ui.field>
                    <x-ui.field label="Website" for="business_website" optional :error="$businessErrors->first('business_website')">
                        <input id="business_website" name="business_website" type="text" inputmode="url" autocomplete="url" placeholder="yourbusiness.com" value="{{ old('business_website', $user->business_website) }}" class="{{ $input }} {{ $businessErrors->has('business_website') ? $inputError : '' }}">
                    </x-ui.field>
                    <x-ui.field label="Industry or niche" for="business_industry" optional class="sm:col-span-2" :error="$businessErrors->first('business_industry')" hint="Pick one or type your own.">
                        <input id="business_industry" name="business_industry" type="text" list="industries" placeholder="e.g. Finance & investing" value="{{ old('business_industry', $user->business_industry) }}" class="{{ $input }}">
                        <datalist id="industries">
                            @foreach ($industries as $industry)
                                <option value="{{ $industry }}"></option>
                            @endforeach
                        </datalist>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <x-ui.card title="Location & timezone" description="So your national holidays are celebrated and emails arrive at a sensible hour." icon="globe-alt">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Country" for="country" optional class="sm:col-span-2" :error="$businessErrors->first('country')">
                        <select id="country" name="country" class="{{ $input }}">
                            <option value="">Choose your country…</option>
                            @foreach ($countries as $code => $countryName)
                                <option value="{{ $code }}" @selected(old('country', $country) === $code)>{{ $countryName }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="State or region" for="state_region" optional :error="$businessErrors->first('state_region')">
                        <input id="state_region" name="state_region" type="text" autocomplete="address-level1" placeholder="e.g. Lagos" value="{{ old('state_region', $user->state_region) }}" class="{{ $input }}">
                    </x-ui.field>
                    <x-ui.field label="City" for="city" optional :error="$businessErrors->first('city')">
                        <input id="city" name="city" type="text" autocomplete="address-level2" placeholder="e.g. Ikeja" value="{{ old('city', $user->city) }}" class="{{ $input }}">
                    </x-ui.field>
                    <x-ui.field label="Timezone" for="timezone" optional class="sm:col-span-2" :error="$businessErrors->first('timezone')" hint="We fill this in from your country. Birthday and holiday emails arrive around 9am your time.">
                        <select id="timezone" name="timezone" class="{{ $input }}">
                            <option value="">Use my country's main timezone</option>
                            @foreach ($timezones as $identifier => $label)
                                <option value="{{ $identifier }}" @selected(old('timezone', $user->timezone) === $identifier)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button variant="brand" icon="check">Save business details</x-ui.button>
            </div>
        </form>
    </section>

    {{-- ───────────────────────── Security ───────────────────────── --}}
    <section role="tabpanel" id="panel-security" aria-labelledby="tab-security" data-panel="security" @class(['hidden' => $activeTab !== 'security', 'space-y-5'])>
        @if ($fortifyStatus)
            <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <x-heroicon-s-check-circle class="h-5 w-5 text-emerald-600" /> {{ $fortifyStatus }}
            </div>
        @endif

        <x-ui.card title="Password" description="Use at least 8 characters — a short phrase is easier to remember and harder to guess." icon="key">
            <form method="POST" action="{{ route('user-password.update') }}" class="grid gap-5 sm:grid-cols-3" novalidate>
                @csrf @method('PUT')
                <x-ui.field label="Current password" for="current_password" :error="$passwordErrors->first('current_password')">
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="{{ $input }} {{ $passwordErrors->has('current_password') ? $inputError : '' }}">
                </x-ui.field>
                <x-ui.field label="New password" for="password" :error="$passwordErrors->first('password')">
                    <input id="password" name="password" type="password" autocomplete="new-password" class="{{ $input }} {{ $passwordErrors->has('password') ? $inputError : '' }}">
                </x-ui.field>
                <x-ui.field label="Confirm new password" for="password_confirmation">
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $input }}">
                </x-ui.field>
                <div class="sm:col-span-3 flex justify-end">
                    <x-ui.button icon="lock-closed">Update password</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Two-factor authentication" description="A code from your phone on top of your password, so a leaked password alone can't get anyone in." icon="device-phone-mobile">
            <x-slot:headerActions>
                @if ($user->hasEnabledTwoFactorAuthentication())
                    <x-ui.status-pill status="verified" label="On" />
                @else
                    <x-ui.status-pill status="not_connected" label="Off" />
                @endif
            </x-slot:headerActions>

            @if ($user->hasEnabledTwoFactorAuthentication())
                <p class="text-sm text-ink-600 mb-4">Your account asks for an authenticator code every time you sign in.</p>

                @if ($recoveryCodes)
                    <details class="mb-4 rounded-xl border border-line p-4">
                        <summary class="cursor-pointer text-sm font-medium text-brand-600 hover:text-brand-700">View recovery codes</summary>
                        <p class="text-xs text-ink-600 mt-2 mb-3">Store these somewhere safe. Each one can be used once if you lose access to your authenticator app.</p>
                        <div class="grid grid-cols-2 gap-1.5 rounded-lg bg-surface-muted p-3 font-mono text-xs">
                            @foreach ($recoveryCodes as $code)
                                <span>{{ $code }}</span>
                            @endforeach
                        </div>
                    </details>
                @endif

                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <x-ui.button variant="secondary" size="sm" icon="arrow-path">Regenerate recovery codes</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf @method('DELETE')
                        <x-ui.button variant="danger" size="sm" icon="no-symbol">Disable 2FA</x-ui.button>
                    </form>
                </div>
            @elseif ($qrSvg)
                <div class="grid gap-5 sm:grid-cols-[auto_1fr] sm:items-start">
                    <div class="inline-block rounded-xl border border-line bg-white p-3">{!! $qrSvg !!}</div>
                    <div>
                        <p class="text-sm text-ink-600 mb-3">Scan this with Google Authenticator, Authy or 1Password, then enter the 6-digit code to confirm.</p>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex flex-wrap gap-2">
                            @csrf
                            <label for="two-factor-code" class="sr-only">6-digit code</label>
                            <input id="two-factor-code" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" required class="{{ $input }} w-40">
                            <x-ui.button icon="check">Confirm</x-ui.button>
                        </form>
                        @if ($errors->getBag('confirmTwoFactorAuthentication')->has('code'))
                            <p class="mt-2 text-xs text-red-600">{{ $errors->getBag('confirmTwoFactorAuthentication')->first('code') }}</p>
                        @endif
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
                            @csrf @method('DELETE')
                            <button class="text-xs text-ink-400 hover:text-ink-600">Cancel setup</button>
                        </form>
                    </div>
                </div>
            @else
                <p class="text-sm text-ink-600 mb-4">Add an extra layer of security with an authenticator app. Strongly recommended, especially if you manage a team.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <x-ui.button icon="shield-check">Enable two-factor authentication</x-ui.button>
                </form>
            @endif
        </x-ui.card>

        <x-ui.card title="Delete account" description="Deactivate your account and stop all billing." icon="trash" class="border-red-200">
            <p class="text-sm text-ink-600 mb-4">
                This deactivates your account immediately: you won't be able to sign in, and no further credits will be used.
                It's kept for 30 days in case you change your mind (contact support during that window to have it restored).
                After 30 days it's permanently deleted.
            </p>
            <form method="POST" action="{{ route('profile.destroy') }}"
                  onsubmit="return confirm('Delete your account? You\'ll be signed out immediately and have 30 days to contact support if you change your mind.');"
                  class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                @method('DELETE')
                <x-ui.field label="Confirm your password" for="delete-password" class="sm:w-72" :error="$errors->first('password')">
                    <input id="delete-password" type="password" name="password" required autocomplete="current-password" class="{{ $input }} {{ $errors->has('password') ? $inputError : '' }}">
                </x-ui.field>
                <x-ui.button variant="danger" icon="trash">Delete my account</x-ui.button>
            </form>
        </x-ui.card>
    </section>

    {{-- ───────────────────────── Notifications ───────────────────────── --}}
    <section role="tabpanel" id="panel-notifications" aria-labelledby="tab-notifications" data-panel="notifications" @class(['hidden' => $activeTab !== 'notifications'])>
        <form method="POST" action="{{ route('profile.notifications.update') }}" class="space-y-5">
            @csrf @method('PUT')
            <x-ui.card title="Email preferences" description="Choose which emails you'd like from us." icon="envelope">
                <div class="space-y-3">
                    <x-ui.toggle name="notify_email_on_completion" icon="bolt"
                                 label="Content ready alerts"
                                 description="An email when a long-running job finishes, like product research or a UGC video, so you don't have to keep the tab open."
                                 :checked="(bool) $user->notify_email_on_completion" />
                    <x-ui.toggle name="marketing_emails_opt_in" icon="gift"
                                 label="Birthday, holiday and tips emails"
                                 description="A birthday surprise, greetings for the festivals you celebrate, and the occasional nudge with tips to get more from your account."
                                 :checked="$user->marketingEmailsEnabled()" />
                </div>
                <p class="mt-4 flex items-start gap-2 text-xs text-ink-400">
                    <x-heroicon-m-information-circle class="h-4 w-4 shrink-0" />
                    Important account emails (receipts, security alerts, password resets and replies to your support tickets) are always sent.
                </p>
            </x-ui.card>
            <div class="flex justify-end">
                <x-ui.button variant="brand" icon="check">Save preferences</x-ui.button>
            </div>
        </form>
    </section>

    @if ($completeness['missing'])
        <x-ui.next-step class="mt-6" :href="route('profile', ['tab' => $completeness['missing'][0]['tab']])" action="Do it now">
            {{ $completeness['missing'][0]['label'] }} — it takes a few seconds and makes everything we send you feel more personal.
        </x-ui.next-step>
    @else
        <x-ui.next-step class="mt-6" :href="route('dashboard')" action="Go to dashboard">
            Your profile is all set. Head back to your dashboard and keep building.
        </x-ui.next-step>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            const tabs = Array.from(document.querySelectorAll('[data-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-panel]'));
            const activeClasses = ['bg-navy-900', 'text-white', 'shadow-sm'];
            const idleClasses = ['text-ink-600', 'hover:bg-surface-muted', 'hover:text-navy-900'];

            function activate(name, focus) {
                tabs.forEach(function (tab) {
                    const on = tab.dataset.tab === name;
                    tab.setAttribute('aria-selected', on ? 'true' : 'false');
                    tab.tabIndex = on ? 0 : -1;
                    activeClasses.forEach(function (c) { tab.classList.toggle(c, on); });
                    idleClasses.forEach(function (c) { tab.classList.toggle(c, !on); });
                    if (on && focus) { tab.focus(); }
                });
                panels.forEach(function (panel) { panel.classList.toggle('hidden', panel.dataset.panel !== name); });
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                window.history.replaceState({}, '', url);
            }

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () { activate(tab.dataset.tab, false); });
                tab.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                        e.preventDefault();
                        const next = tabs[(index + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                        activate(next.dataset.tab, true);
                    }
                });
            });

            document.querySelectorAll('[data-tab-link]').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    activate(link.dataset.tabLink, true);
                    document.getElementById('tab-' + link.dataset.tabLink).scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            // Upload the photo the moment one is picked.
            const photoInput = document.getElementById('photo-input');
            if (photoInput) {
                photoInput.addEventListener('change', function () {
                    if (photoInput.files.length) { document.getElementById('photo-form').submit(); }
                });
            }

            // Preselect the timezone (and phone country code) from the chosen country.
            const countryTimezones = @json($countryTimezones);
            const country = document.getElementById('country');
            const timezone = document.getElementById('timezone');
            const phoneCountry = document.getElementById('phone_country');
            let timezoneTouched = timezone && timezone.value !== '';
            if (timezone) {
                timezone.addEventListener('change', function () { timezoneTouched = timezone.value !== ''; });
            }
            if (country && timezone) {
                country.addEventListener('change', function () {
                    const tz = countryTimezones[country.value];
                    if (tz && !timezoneTouched && timezone.querySelector('option[value="' + tz + '"]')) {
                        timezone.value = tz;
                    }
                    if (phoneCountry && phoneCountry.querySelector('option[value="' + country.value + '"]') && !document.getElementById('phone_number').value) {
                        phoneCountry.value = country.value;
                    }
                });
            }
        })();
    </script>
@endpush
