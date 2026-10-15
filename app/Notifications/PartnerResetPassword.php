<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Laravel's password reset email, pointing partners at the Partner
 * Portal's own reset page on the affiliate host instead of the customer
 * app's. See User::sendPasswordResetNotification().
 */
class PartnerResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return route('partner.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
