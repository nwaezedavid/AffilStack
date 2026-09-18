<?php

namespace App\Services\Referrals;

use App\Models\AffiliateApplication;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\AffiliateApplicationApproved;
use App\Notifications\AffiliateApplicationReceived;
use App\Notifications\AffiliateApplicationRejected;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * "Anyone can sign-up to become an affiliate without first becoming a user
 * of the platform. Users are automatically affiliates, but others will have
 * to be manually approved by the admin after they submit their
 * application." The whole lifecycle for the second half of that sentence —
 * an existing customer is already an affiliate the moment they log in (see
 * User::referralCode()/dashboard.referrals.index), so this service only
 * ever runs for someone who was never a customer at all.
 */
class AffiliateApplicationService
{
    /**
     * @param  array{name: string, email: string, phone: ?string, promotion_channels: string, message: ?string}  $data
     */
    public function submit(array $data): AffiliateApplication
    {
        if (! SiteSetting::flag('affiliate_program_enabled')) {
            throw new InvalidArgumentException('Our affiliate program isn\'t accepting new applications right now.');
        }

        if (User::where('email', $data['email'])->exists()) {
            throw new InvalidArgumentException('An account with this email already exists — you\'re already an affiliate automatically. Just log in to find your referral link.');
        }

        if (AffiliateApplication::where('email', $data['email'])->where('status', 'pending')->exists()) {
            throw new InvalidArgumentException('You already have an application under review for this email — we\'ll be in touch soon.');
        }

        $application = AffiliateApplication::create([
            ...$data,
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        $supportEmail = SiteSetting::get('support_email', 'support@affilstack.com');

        if ($supportEmail) {
            Notification::route('mail', $supportEmail)->notify(new AffiliateApplicationReceived($application));
        }

        return $application;
    }

    /**
     * Creates the affiliate-only User account (see
     * User::isAffiliateOnly()/RestrictAffiliateOnlyAccounts), locked with an
     * unusable random password until the applicant sets their own through
     * the emailed link.
     */
    public function approve(AffiliateApplication $application, User $admin): User
    {
        if (! $application->isPending()) {
            throw new InvalidArgumentException('Only a pending application can be approved.');
        }

        $user = User::create([
            'name' => $application->name,
            'email' => $application->email,
            'password' => Hash::make(Str::random(40)),
            'is_affiliate_only' => true,
        ]);
        $user->assignRole('user');

        $token = Str::random(64);

        $application->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by_id' => $admin->id,
            'approved_user_id' => $user->id,
            'set_password_token' => hash('sha256', $token),
            'set_password_expires_at' => now()->addDays(7),
        ]);

        $setPasswordUrl = route('affiliate.set-password.show', ['application' => $application->id, 'token' => $token]);

        $user->notify(new AffiliateApplicationApproved($application, $setPasswordUrl));

        return $user;
    }

    public function reject(AffiliateApplication $application, User $admin, string $reason): void
    {
        if (! $application->isPending()) {
            throw new InvalidArgumentException('Only a pending application can be rejected.');
        }

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by_id' => $admin->id,
        ]);

        Notification::route('mail', $application->email)->notify(new AffiliateApplicationRejected($application));
    }

    /**
     * Sets the new password and single-uses the link — called only after
     * AffiliateApplication::hasValidSetPasswordToken() has already been
     * checked by the controller.
     */
    public function setPassword(AffiliateApplication $application, string $password): void
    {
        if (! $application->approvedUser) {
            throw new InvalidArgumentException('This application has no account to set a password for.');
        }

        $application->approvedUser->update(['password' => Hash::make($password)]);

        $application->update([
            'set_password_token' => null,
            'set_password_expires_at' => null,
        ]);
    }
}
