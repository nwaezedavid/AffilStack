<?php

namespace App\Console\Commands\Crm;

use App\Models\CrmCampaign;
use App\Models\EmailConnection;
use App\Services\Crm\CampaignManager;
use App\Services\Crm\ConnectionVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily re-check of every email connection (SMTP handshake + AUTH, Gmail
 * token, IMAP login) so the Email Sending page's indicators stay honest.
 * A sender that stops working pauses its user's running campaigns (and
 * tells them) instead of failing every send one by one.
 */
#[Signature('crm:verify-email-connections')]
#[Description('Re-verify every connected Gmail/SMTP sender and IMAP mailbox')]
class VerifyEmailConnections extends Command
{
    public function handle(ConnectionVerifier $verifier, CampaignManager $manager): void
    {
        $failed = 0;
        $checked = 0;
        $deadline = microtime(true) + 50;

        EmailConnection::query()->with('user')->orderBy('last_checked_at')->limit(300)->get()
            ->each(function (EmailConnection $connection) use ($verifier, $manager, &$failed, &$checked, $deadline) {
                if (microtime(true) >= $deadline) {
                    return false;
                }

                $checked++;
                $wasVerified = $connection->isVerified();

                if (! $verifier->verifySending($connection)) {
                    $failed++;

                    if ($wasVerified) {
                        CrmCampaign::where('user_id', $connection->user_id)
                            ->where(fn ($q) => $q->where('status', 'sending')->orWhere(fn ($q) => $q->where('status', 'sent')->where('autopilot', true)))
                            ->get()
                            ->each(fn (CrmCampaign $campaign) => $manager->pauseForConnection($campaign));
                    }
                }

                if ($connection->hasImap()) {
                    $verifier->verifyImap($connection);
                }
            });

        $this->info("Checked {$checked} connection(s); {$failed} failed.");
    }
}
