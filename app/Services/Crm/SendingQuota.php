<?php

namespace App\Services\Crm;

use App\Models\CrmEmailSend;
use App\Models\User;

/**
 * Per-user daily sending cap across every CRM send (campaigns, follow-ups,
 * nurture steps, inbox replies) — the user's own connected sender's cap
 * (EmailConnection::dailyCap(), configurable in config/crm.php and lowered
 * by the user), or the much smaller platform cap without a connection.
 */
class SendingQuota
{
    public function cap(User $user): int
    {
        $connection = $user->emailConnection;

        return $connection ? $connection->dailyCap() : (int) config('crm.daily_caps.platform', 50);
    }

    public function sentToday(User $user): int
    {
        return CrmEmailSend::where('user_id', $user->id)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();
    }

    public function remaining(User $user): int
    {
        return max(0, $this->cap($user) - $this->sentToday($user));
    }
}
