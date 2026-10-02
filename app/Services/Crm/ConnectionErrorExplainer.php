<?php

namespace App\Services\Crm;

/**
 * Turns a raw SMTP/IMAP/Gmail error into one friendly sentence a
 * non-technical user can act on. Raw server text is never shown (it can
 * echo server banners); it stays in the log.
 */
class ConnectionErrorExplainer
{
    /**
     * @param  string  $kind  'smtp' | 'imap' | 'gmail'
     */
    public function explain(string $error, string $kind, string $host = ''): string
    {
        $e = strtolower($error);
        $host = strtolower($host);
        $isGmail = str_contains($host, 'gmail') || str_contains($host, 'google');
        $isMicrosoft = str_contains($host, 'office365') || str_contains($host, 'outlook') || str_contains($host, 'hotmail');
        $isYahoo = str_contains($host, 'yahoo');

        if ($kind === 'gmail') {
            return 'Google no longer accepts AffilStack\'s permission for this Gmail account (it may have been revoked or expired). Click "Reconnect Gmail" to fix it.';
        }

        if (str_contains($e, 'application-specific password') || str_contains($e, 'app password') || str_contains($e, '534-5.7.9') || str_contains($e, 'webloginrequired')) {
            return 'Your provider requires an App Password here, not your normal password. Create one in your account\'s security settings and paste it in.';
        }

        if ($this->isAuthFailure($e)) {
            return match (true) {
                $isGmail => 'Gmail rejected the password — use a 16-character App Password (Google Account → Security → App passwords), not your normal Gmail password.',
                $isMicrosoft => 'Microsoft rejected the login. Use your full email address, and an app password if MFA is on; your admin may also need to enable "Authenticated SMTP"'.($kind === 'imap' ? ' / IMAP' : '').' for this mailbox.',
                $isYahoo => 'Yahoo rejected the password — Yahoo requires an app password (Account security → Generate app password).',
                default => 'The server rejected the username or password. Use your full email address as the username and double-check the password (or app password).',
            };
        }

        if (str_contains($e, 'certificate') || str_contains($e, 'ssl') || str_contains($e, 'tls') || str_contains($e, 'crypto')) {
            return 'A secure (TLS) connection could not be set up. Check the encryption setting matches the port: SSL/TLS on 465 (SMTP) or 993 (IMAP), STARTTLS on 587 (SMTP) or 143 (IMAP).';
        }

        if (str_contains($e, 'timed out') || str_contains($e, 'timeout')) {
            return 'The server didn\'t answer in time. Check the host name and port — many hosts block port 25, so use 465 or 587 for sending and 993 for IMAP.';
        }

        if (str_contains($e, 'could not be resolved') || str_contains($e, 'getaddrinfo') || str_contains($e, 'name or service not known') || str_contains($e, 'public internet host')) {
            return 'That server address doesn\'t exist or isn\'t a public mail server. Check the host name for typos (e.g. smtp.hostinger.com).';
        }

        if (str_contains($e, 'refused') || str_contains($e, 'connection could not be established') || str_contains($e, 'unable to connect') || str_contains($e, 'could not connect')) {
            return 'We couldn\'t reach that server on that port. Check the host and port from your email provider\'s settings page.';
        }

        if (str_contains($e, 'starttls')) {
            return 'This server doesn\'t support STARTTLS on that port — switch to SSL/TLS (port 465 for SMTP, 993 for IMAP).';
        }

        if (str_contains($e, 'sender') || str_contains($e, 'not owned') || str_contains($e, 'not allowed to send')) {
            return 'The server won\'t send as that "From" address — it must be the same mailbox you log in with (or an alias you own).';
        }

        if ($kind === 'imap' && (str_contains($e, 'imap') && str_contains($e, 'disabled'))) {
            return 'IMAP access is turned off for this mailbox — enable IMAP in your email settings, then test again.';
        }

        return $kind === 'imap'
            ? 'We couldn\'t open your inbox with these settings. Double-check the IMAP host, port and password.'
            : 'We couldn\'t send with these settings. Double-check the SMTP host, port, encryption and password.';
    }

    protected function isAuthFailure(string $e): bool
    {
        foreach (['535', 'authenticat', 'invalid credentials', 'username and password not accepted', 'login failed', 'logon failure', 'auth', 'invalid login'] as $needle) {
            if (str_contains($e, $needle)) {
                return true;
            }
        }

        return false;
    }
}
