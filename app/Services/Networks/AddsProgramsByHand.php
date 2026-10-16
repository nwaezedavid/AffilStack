<?php

namespace App\Services\Networks;

use App\Models\NetworkConnection;

/**
 * Networks with no marketplace API (ClickBank, Digistore24): the customer
 * adds a product by its ID from the network's marketplace and AffilStack
 * builds the tracked link.
 */
interface AddsProgramsByHand
{
    /**
     * What the customer types, e.g. "ClickBank vendor nickname".
     */
    public function manualProgramLabel(): string;

    /**
     * @return array<string, mixed> program data, as NetworkAdapter::programs() returns
     *
     * @throws NetworkException
     */
    public function programByHand(NetworkConnection $connection, string $input): array;
}
