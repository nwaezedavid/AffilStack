<?php

namespace App\Services\Referrals;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A thin, connection-only client for PartnerStack's REST API
 * (api.partnerstack.com/v1, Basic Auth with public_key:secret_key — see
 * https://docs.partnerstack.com/v1.0/docs/authentication). This is
 * deliberately limited to verifyCredentials() for now: PartnerStack is
 * architected as a full system-of-record for an affiliate program rather
 * than a peer you sync an existing in-house ledger with, so no
 * import/export methods exist yet — see PartnerStackSetting's docblock.
 * Real sync methods (pushing conversions via the Actions API, or a
 * one-time export into PartnerStack's format) get added here once that
 * direction is chosen.
 */
class PartnerStackClient
{
    protected const BASE_URL = 'https://api.partnerstack.com/v1';

    public function __construct(
        protected string $publicKey,
        protected string $secretKey,
        protected int $timeout = 15,
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyCredentials(): array
    {
        try {
            $response = Http::withBasicAuth($this->publicKey, $this->secretKey)
                ->timeout($this->timeout)
                ->acceptJson()
                ->get(self::BASE_URL.'/partnerships');
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach PartnerStack: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'PartnerStack API key is valid.'];
        }

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'PartnerStack rejected these credentials (401 Unauthorized).'];
        }

        return ['success' => false, 'message' => 'PartnerStack returned an unexpected response: HTTP '.$response->status()];
    }
}
