<?php

namespace App\Services\ApiWallet;

use RuntimeException;

/**
 * Thrown by ApiWalletManager::charge() when a metered API call can't be
 * paid for — either there's no auto-recharge configured, or an attempted
 * auto-recharge itself failed (declined card, unsupported gateway). Caught
 * by MeterApiUsage to turn into a 402 response rather than a 500.
 */
class InsufficientApiWalletBalanceException extends RuntimeException
{
    //
}
