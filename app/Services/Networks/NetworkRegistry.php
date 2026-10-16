<?php

namespace App\Services\Networks;

use App\Services\Networks\Adapters\AwinAdapter;
use App\Services\Networks\Adapters\CjAdapter;
use App\Services\Networks\Adapters\ClickBankAdapter;
use App\Services\Networks\Adapters\Digistore24Adapter;
use App\Services\Networks\Adapters\ImpactAdapter;
use App\Services\Networks\Adapters\PartnerStackAdapter;
use InvalidArgumentException;

/**
 * The affiliate networks AffilStack connects to (config/networks.php) and
 * the adapter that speaks each one's API.
 */
class NetworkRegistry
{
    /** @var array<string, class-string<NetworkAdapter>> */
    public const ADAPTERS = [
        'impact' => ImpactAdapter::class,
        'partnerstack' => PartnerStackAdapter::class,
        'awin' => AwinAdapter::class,
        'cj' => CjAdapter::class,
        'clickbank' => ClickBankAdapter::class,
        'digistore24' => Digistore24Adapter::class,
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::ADAPTERS);
    }

    public static function exists(string $network): bool
    {
        return isset(self::ADAPTERS[$network]);
    }

    public static function adapter(string $network): NetworkAdapter
    {
        if (! self::exists($network)) {
            throw new InvalidArgumentException("Unknown affiliate network [{$network}].");
        }

        return app(self::ADAPTERS[$network]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(string $network): array
    {
        return (array) config("networks.networks.{$network}", []);
    }

    public static function label(string $network): string
    {
        return (string) (self::config($network)['label'] ?? ucfirst($network));
    }

    /**
     * The adapter for whichever network made this link, if any.
     */
    public static function forUrl(string $url): ?NetworkAdapter
    {
        foreach (self::keys() as $network) {
            $adapter = self::adapter($network);

            if ($adapter->recognises($url)) {
                return $adapter;
            }
        }

        return null;
    }
}
