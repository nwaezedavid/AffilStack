<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\EmailAddressChanged;
use App\Services\Media\AvatarProcessor;
use App\Services\Payments\StripeGateway;
use App\Support\Countries;
use App\Support\Lifecycle\LifecycleAudience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Profile & Security: a tabbed page (Personal, Business, Security,
 * Notifications). Each tab's form posts to its own action here so a
 * validation error on one never loses what was typed on another. The
 * birth date, country, timezone and religion saved here drive the
 * birthday and festive-season emails (see LifecycleEmailDispatcher).
 */
class ProfileController extends Controller
{
    /**
     * @var array<int, string>
     */
    public const TABS = ['personal', 'business', 'security', 'notifications'];

    public function show(Request $request): View
    {
        $user = auth()->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'personal';

        $qrSvg = null;
        $recoveryCodes = null;

        if ($user->two_factor_secret && ! $user->two_factor_confirmed_at) {
            $qrSvg = $user->twoFactorQrCodeSvg();
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $recoveryCodes = $user->recoveryCodes();
        }

        [$phoneCountry, $phoneNumber] = $this->splitPhone($user);
        $country = Countries::normalize($user->country);

        return view('dashboard.profile', [
            'user' => $user,
            'qrSvg' => $qrSvg,
            'recoveryCodes' => $recoveryCodes,
            'tab' => $tab,
            'country' => $country,
            'phoneCountry' => $phoneCountry ?? $country ?? 'NG',
            'phoneNumber' => $phoneNumber,
            'birthDay' => $user->birth_date?->day,
            'birthMonth' => $user->birth_date?->month,
            'birthYear' => LifecycleAudience::hasBirthYear($user) ? $user->birth_date->year : null,
            'completeness' => $this->completeness($user),
            'countries' => Countries::options(),
            'dialCodes' => Countries::dialCodeOptions(),
            'timezones' => Countries::timezoneOptions(),
            'countryTimezones' => collect(Countries::options())->keys()->mapWithKeys(fn (string $code) => [$code => Countries::mainTimezone($code)])->all(),
            'countryDialCodes' => collect(Countries::options())->keys()->mapWithKeys(fn (string $code) => [$code => Countries::dialCode($code)])->all(),
        ]);
    }

    public function updatePersonal(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('personal', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'birth_day' => ['nullable', 'integer', 'between:1,31', 'required_with:birth_month'],
            'birth_month' => ['nullable', 'integer', 'between:1,12', 'required_with:birth_day'],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'phone_country' => ['nullable', 'string', Rule::in(array_keys(Countries::dialCodeOptions()))],
            'phone_number' => ['nullable', 'string', 'max:25', 'regex:/^\+?[0-9][0-9 ()\-.]{3,22}$/'],
            'gender' => ['nullable', Rule::in(array_keys(LifecycleAudience::GENDERS))],
            'religion' => ['nullable', Rule::in(array_keys(LifecycleAudience::RELIGIONS))],
            // Changing the sign-in email needs the current password, so a
            // borrowed session can't move the account to someone else's
            // address. Google-linked accounts are protected by their Google
            // link instead (they may never have chosen a password).
            'current_password' => [Rule::requiredIf(fn () => strcasecmp((string) $request->input('email'), (string) $user->email) !== 0 && ! $user->google_id), 'nullable', 'current_password'],
        ], [
            'current_password.required' => 'Enter your current password to change your email address.',
            'current_password.current_password' => 'That password is not correct.',
            'birth_day.required_with' => 'Choose the day of your birthday too.',
            'birth_month.required_with' => 'Choose the month of your birthday too.',
            'phone_number.regex' => 'Enter a phone number using digits only (spaces and dashes are fine).',
        ], [
            'birth_day' => 'birthday day',
            'birth_month' => 'birthday month',
            'birth_year' => 'birth year',
        ]);

        $birthDate = null;

        if (filled($validated['birth_day'] ?? null) && filled($validated['birth_month'] ?? null)) {
            $year = (int) ($validated['birth_year'] ?? LifecycleAudience::NO_BIRTH_YEAR);

            if (! checkdate((int) $validated['birth_month'], (int) $validated['birth_day'], $year)) {
                throw ValidationException::withMessages(['birth_day' => 'That date doesn\'t exist — check the day and month.'])->errorBag('personal');
            }

            if ($year !== LifecycleAudience::NO_BIRTH_YEAR && now()->setDate($year, (int) $validated['birth_month'], (int) $validated['birth_day'])->isFuture()) {
                throw ValidationException::withMessages(['birth_year' => 'Your birthday can\'t be in the future.'])->errorBag('personal');
            }

            $birthDate = sprintf('%04d-%02d-%02d', $year, $validated['birth_month'], $validated['birth_day']);
        }

        $previousEmail = $user->email;

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'birth_date' => $birthDate,
            'phone' => $this->composePhone($validated['phone_country'] ?? null, $validated['phone_number'] ?? null),
            'gender' => $validated['gender'] ?? null,
            'religion' => $validated['religion'] ?? null,
        ])->save();

        if (strcasecmp($previousEmail, $user->email) !== 0) {
            Notification::route('mail', $previousEmail)->notify(new EmailAddressChanged($user->email));
        }

        return redirect()->route('profile', ['tab' => 'personal'])->with('success', 'Your personal details are saved.');
    }

    public function updatePhoto(Request $request, AvatarProcessor $avatars): RedirectResponse
    {
        $request->validateWithBag('photo', [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120', 'dimensions:min_width=64,min_height=64'],
        ], [
            'photo.dimensions' => 'Please choose a photo at least 64×64 pixels.',
            'photo.max' => 'Please choose a photo under 5 MB.',
        ]);

        $user = $request->user();

        try {
            $path = $avatars->store($request->file('photo'), $user->id);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['photo' => $e->getMessage()])->errorBag('photo');
        }

        $previous = $user->avatar_path;
        $user->forceFill(['avatar_path' => $path])->save();
        $avatars->delete($previous);

        return redirect()->route('profile', ['tab' => 'personal'])->with('success', 'Looking good! Your new profile photo is saved.');
    }

    public function destroyPhoto(Request $request, AvatarProcessor $avatars): RedirectResponse
    {
        $user = $request->user();
        $avatars->delete($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();

        return redirect()->route('profile', ['tab' => 'personal'])->with('success', 'Your profile photo was removed.');
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $request->merge([
            'business_website' => $this->normalizeWebsite($request->input('business_website')),
        ]);

        $validated = $request->validateWithBag('business', [
            'company_name' => ['nullable', 'string', 'max:120'],
            'business_website' => ['nullable', 'url:http,https', 'max:255'],
            'business_industry' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', Rule::in(array_keys(Countries::options()))],
            'state_region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ], [], [
            'company_name' => 'business name',
            'business_website' => 'website',
            'business_industry' => 'industry',
            'state_region' => 'state or region',
        ]);

        $country = $validated['country'] ?? null;
        $timezone = $validated['timezone'] ?? null;

        if (! $timezone && $country) {
            $timezone = Countries::mainTimezone($country);
        }

        $request->user()->forceFill([
            'company_name' => $validated['company_name'] ?? null,
            'business_website' => $validated['business_website'] ?? null,
            'business_industry' => $validated['business_industry'] ?? null,
            'country' => $country,
            'state_region' => $validated['state_region'] ?? null,
            'city' => $validated['city'] ?? null,
            'timezone' => $timezone,
        ])->save();

        return redirect()->route('profile', ['tab' => 'business'])->with('success', 'Your business details are saved.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'notify_email_on_completion' => $request->boolean('notify_email_on_completion'),
            'marketing_emails_opt_in' => $request->boolean('marketing_emails_opt_in'),
        ])->save();

        return redirect()->route('profile', ['tab' => 'notifications'])->with('success', 'Your email preferences are saved.');
    }

    /**
     * How much of the profile is filled in (0–100) and what's missing,
     * for the completeness meter and "what to do next" nudge.
     *
     * @return array{percent: int, missing: array<int, array{label: string, tab: string}>}
     */
    protected function completeness(User $user): array
    {
        $checks = [
            ['label' => 'Add a profile photo', 'tab' => 'personal', 'done' => filled($user->avatar_path)],
            ['label' => 'Add your birthday', 'tab' => 'personal', 'done' => filled($user->birth_date)],
            ['label' => 'Add your phone number', 'tab' => 'personal', 'done' => filled($user->phone)],
            ['label' => 'Add your business name', 'tab' => 'business', 'done' => filled($user->company_name)],
            ['label' => 'Add your website', 'tab' => 'business', 'done' => filled($user->business_website)],
            ['label' => 'Choose your country', 'tab' => 'business', 'done' => Countries::normalize($user->country) !== null],
            ['label' => 'Set your timezone', 'tab' => 'business', 'done' => filled($user->timezone)],
            ['label' => 'Turn on two-factor authentication', 'tab' => 'security', 'done' => $user->hasEnabledTwoFactorAuthentication()],
        ];

        $done = collect($checks)->where('done', true)->count();

        return [
            'percent' => (int) round($done / count($checks) * 100),
            'missing' => collect($checks)->where('done', false)->map(fn ($check) => ['label' => $check['label'], 'tab' => $check['tab']])->values()->all(),
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string} [ISO country of the dial code, local number]
     */
    protected function splitPhone(User $user): array
    {
        $phone = trim((string) $user->phone);

        if ($phone === '') {
            return [null, null];
        }

        if (preg_match('/^(\+\d{1,4})\s+(.+)$/', $phone, $m)) {
            $preferred = Countries::normalize($user->country);

            if ($preferred && Countries::dialCode($preferred) === $m[1]) {
                return [$preferred, $m[2]];
            }

            foreach (Countries::dialCodeOptions() as $code => $label) {
                if (Countries::dialCode($code) === $m[1]) {
                    return [$code, $m[2]];
                }
            }
        }

        return [null, $phone];
    }

    protected function composePhone(?string $country, ?string $number): ?string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        if (str_starts_with($number, '+')) {
            return $number;
        }

        $dial = Countries::dialCode($country);

        return $dial ? $dial.' '.ltrim($number, '0 ') : $number;
    }

    protected function normalizeWebsite(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '') {
            return null;
        }

        return Str::startsWith(Str::lower($website), ['http://', 'https://']) ? $website : 'https://'.$website;
    }

    /**
     * Audit gap #4: self-service account deletion, soft-delete + 30-day
     * grace period. Soft-deleting immediately makes the account invisible
     * to every normal User:: lookup the app makes (Fortify's login query
     * included) via Eloquent's global SoftDeletes scope — nothing else
     * needs to change to "block login" or "freeze credits". A daily
     * command (see PurgeDeletedAccounts) hard-deletes it 30 days later
     * unless an admin restores it first from the Users resource.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Stop everything that keeps running without a browser session:
        // Stripe would otherwise keep charging a deactivated account every
        // month, bearer tokens would keep working until the 30-day purge,
        // and team seats would keep using the owner's plan.
        $subscription = $user->activeSubscription;

        if ($subscription) {
            if ($subscription->gateway === 'stripe' && $subscription->gateway_subscription_id) {
                try {
                    app(StripeGateway::class)->cancelSubscription($subscription->gateway_subscription_id);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        }

        $user->apiTokens()->delete();
        $user->seats()->update(['is_suspended' => true]);

        $user->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Your account has been deactivated. Contact support within the next 30 days if you\'d like it restored.');
    }
}
