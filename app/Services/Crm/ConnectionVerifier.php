<?php

namespace App\Services\Crm;

use App\Models\EmailConnection;
use App\Services\Crm\Imap\ImapClient;
use App\Services\Crm\Imap\ImapException;
use App\Services\Crm\Imap\ImapTransport;
use RuntimeException;
use Throwable;

/**
 * The live checks behind the Email Sending page's verification pills: a
 * real SMTP handshake + AUTH (optionally followed by a test email to the
 * user), a real IMAP LOGIN + SELECT INBOX, or a Gmail token refresh +
 * profile read. Every result is stored on the connection (status, message,
 * time) and failures get a plain-English explanation.
 */
class ConnectionVerifier
{
    public function __construct(
        protected SmtpProbe $smtp,
        protected PersonalEmailSender $sender,
        protected GmailOAuthService $gmail,
        protected ConnectionErrorExplainer $explainer,
    ) {}

    /**
     * Verifies the sending side (Gmail or SMTP).
     */
    public function verifySending(EmailConnection $connection, ?string $testRecipient = null): bool
    {
        try {
            if ($connection->isGmail()) {
                $email = $this->gmail->verify($connection);
                $message = "Connected as {$email} — Google accepted the sending permission.";
            } else {
                $this->smtp->probe([
                    'host' => (string) $connection->credential('host'),
                    'port' => (int) $connection->credential('port'),
                    'username' => (string) $connection->credential('username'),
                    'password' => (string) $connection->credential('password'),
                    'encryption' => $connection->credential('encryption'),
                ]);
                $message = 'Logged in to '.$connection->credential('host').' successfully.';
            }

            if ($testRecipient) {
                $this->sender->send(
                    $connection,
                    $testRecipient,
                    'AffilStack: your email sending works',
                    '<p>Good news — AffilStack can send your CRM campaigns from <strong>'.e((string) $connection->connected_email).'</strong>.</p><p>You can close this email.</p>',
                );
                $message .= ' A test email was sent to '.$testRecipient.'.';
            }

            $connection->update([
                'verified_at' => now(),
                'last_checked_at' => now(),
                'verification_status' => 'success',
                'verification_message' => $message,
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            $connection->update([
                'last_checked_at' => now(),
                'verification_status' => 'failed',
                'verification_message' => $this->explainer->explain($e->getMessage(), $connection->isGmail() ? 'gmail' : 'smtp', (string) $connection->credential('host')),
            ]);

            return false;
        }
    }

    /**
     * Verifies the reply-detection mailbox with LOGIN + SELECT INBOX.
     */
    public function verifyImap(EmailConnection $connection): bool
    {
        if (! $connection->hasImap()) {
            return false;
        }

        $client = new ImapClient(app(ImapTransport::class));

        try {
            $client->connect(
                (string) $connection->imapCredential('host'),
                (int) $connection->imapCredential('port', 993),
                (string) $connection->imapCredential('encryption', 'ssl'),
                (int) config('crm.replies.imap_timeout', 15),
            );
            $client->login((string) $connection->imapCredential('username'), (string) $connection->imapCredential('password'));
            $mailbox = $client->select('INBOX');
            $client->logout();

            $updates = [
                'imap_status' => 'success',
                'imap_verified_at' => now(),
                'imap_message' => 'Inbox opened ('.number_format($mailbox['exists']).' messages) — we\'ll check it for replies every 5 minutes.',
            ];

            if ($connection->imap_uidvalidity === null || (int) $connection->imap_uidvalidity !== $mailbox['uidvalidity']) {
                $updates['imap_uidvalidity'] = $mailbox['uidvalidity'];
                $updates['imap_last_uid'] = null;
            }

            $connection->update($updates);

            return true;
        } catch (ImapException|RuntimeException $e) {
            report($e);

            $connection->update([
                'imap_status' => 'failed',
                'imap_message' => $this->explainer->explain($e->getMessage(), 'imap', (string) $connection->imapCredential('host')),
            ]);

            return false;
        }
    }
}
