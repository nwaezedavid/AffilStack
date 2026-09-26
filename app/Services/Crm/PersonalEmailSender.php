<?php

namespace App\Services\Crm;

use App\Models\EmailConnection;
use App\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends one email through a user's own connected sender (task #1) — Gmail
 * via the Gmail API (GmailOAuthService) or their domain's SMTP via a raw,
 * ad-hoc Symfony transport (Laravel's MailManager::build(), never added to
 * config/mail.php since the credentials only exist per-row in
 * email_connections). Used by CrmEmailService once a connection exists,
 * and by EmailConnectionController to send the one-off "test email" a user
 * gets when they first set up SMTP.
 */
class PersonalEmailSender
{
    public function __construct(protected GmailOAuthService $gmail) {}

    public function send(EmailConnection $connection, string $toEmail, string $subject, string $htmlBody): void
    {
        match ($connection->provider) {
            'gmail' => $this->gmail->send($connection, $toEmail, $subject, $htmlBody),
            'smtp' => $this->sendViaSmtp($connection, $toEmail, $subject, $htmlBody),
            default => throw new InvalidArgumentException("Unknown email connection provider: {$connection->provider}"),
        };
    }

    protected function sendViaSmtp(EmailConnection $connection, string $toEmail, string $subject, string $htmlBody): void
    {
        // Re-checked at send time: the hostname's DNS could have been
        // re-pointed at a private address since it was saved.
        try {
            OutboundUrlGuard::resolveSafeHost((string) $connection->credential('host'));
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('SMTP send refused: '.$e->getMessage(), previous: $e);
        }

        $transport = Mail::createSymfonyTransport([
            'transport' => 'smtp',
            'timeout' => 15,
            'host' => $connection->credential('host'),
            'port' => (int) $connection->credential('port'),
            'username' => $connection->credential('username'),
            'password' => $connection->credential('password'),
        ]);

        $fromName = $connection->credential('from_name');
        $fromEmail = (string) $connection->credential('from_email');

        $message = (new Email)
            ->from($fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail)
            ->to($toEmail)
            ->subject($subject)
            ->html($htmlBody);

        try {
            $transport->send($message);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('SMTP send failed: '.$e->getMessage(), previous: $e);
        }
    }
}
