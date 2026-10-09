<?php

namespace App\Services\Crm;

use App\Models\EmailConnection;
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

    /**
     * $options (all optional), used by CRM campaigns and inbox replies so a
     * reply can be matched back and a follow-up lands in the same thread:
     *  - message_id: our own "<id@domain>" Message-ID (stored for matching)
     *  - in_reply_to / references: the Message-ID(s) this email answers
     *  - list_unsubscribe: an https unsubscribe URL (List-Unsubscribe header)
     *  - thread_id: Gmail API thread to post into
     *  - text_body: plain-text alternative
     *
     * Returns the provider's own ids when it reports any (Gmail API), else null.
     *
     * @param  array{message_id?: string, in_reply_to?: string|null, references?: array<int, string>, list_unsubscribe?: string|null, thread_id?: string|null, text_body?: string|null}  $options
     * @return array{provider_message_id: string|null, provider_thread_id: string|null}|null
     */
    public function send(EmailConnection $connection, string $toEmail, string $subject, string $htmlBody, array $options = []): ?array
    {
        return match ($connection->provider) {
            'gmail' => $this->gmail->send($connection, $toEmail, $subject, $htmlBody, $options),
            'smtp' => $this->sendViaSmtp($connection, $toEmail, $subject, $htmlBody, $options),
            default => throw new InvalidArgumentException("Unknown email connection provider: {$connection->provider}"),
        };
    }

    /**
     * Applies the threading/compliance headers in $options to $message.
     *
     * @param  array<string, mixed>  $options
     */
    public static function applyOptions(Email $message, array $options): Email
    {
        $headers = $message->getHeaders();

        if (! empty($options['message_id'])) {
            $headers->addIdHeader('Message-ID', trim($options['message_id'], '<>'));
        }

        if (! empty($options['in_reply_to'])) {
            $headers->addIdHeader('In-Reply-To', trim($options['in_reply_to'], '<>'));
        }

        if (! empty($options['references'])) {
            $headers->addIdHeader('References', array_map(fn (string $id) => trim($id, '<>'), $options['references']));
        }

        if (! empty($options['list_unsubscribe'])) {
            $headers->addTextHeader('List-Unsubscribe', '<'.$options['list_unsubscribe'].'>');
        }

        if (! empty($options['text_body'])) {
            $message->text($options['text_body']);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function sendViaSmtp(EmailConnection $connection, string $toEmail, string $subject, string $htmlBody, array $options = []): ?array
    {
        // Re-checked at send time and pinned to the vetted IP: the hostname's
        // DNS could have been re-pointed at a private address since it was saved.
        try {
            $transport = PinnedSmtpTransport::make([
                'host' => (string) $connection->credential('host'),
                'port' => (int) $connection->credential('port'),
                'username' => $connection->credential('username'),
                'password' => $connection->credential('password'),
                'encryption' => $connection->credential('encryption'),
            ]);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('SMTP send refused: '.$e->getMessage(), previous: $e);
        }

        $fromName = $connection->credential('from_name');
        $fromEmail = (string) $connection->credential('from_email');

        $message = (new Email)
            ->from($fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail)
            ->to($toEmail)
            ->subject($subject)
            ->html($htmlBody);

        self::applyOptions($message, $options);

        try {
            $transport->send($message);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('SMTP send failed: '.$e->getMessage(), previous: $e);
        }

        return null;
    }
}
