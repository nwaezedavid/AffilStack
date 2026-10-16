<?php

namespace App\Services\Networks;

use App\Models\NetworkProgram;
use App\Models\Offer;
use App\Models\User;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\OfferResearchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Network-made tracked links on products: adding a product straight from
 * a joined program, and switching an existing product to its program's
 * tracked link (deep-linked to the product page where the program allows
 * it). The customer's own affiliate link is never touched, so they can
 * switch back at any time.
 */
class NetworkLinkService
{
    public function __construct(protected OfferResearchService $research) {}

    /**
     * Creates a product (with offer research, like any new product) from a
     * joined program and gives it the network's tracked link.
     *
     * @throws InvalidArgumentException|InsufficientCreditsException|NetworkException
     */
    public function importProgram(User $user, NetworkProgram $program): Offer
    {
        if (! $program->isJoined()) {
            throw new InvalidArgumentException('Join this program on '.$program->connection->label().' first, then add it.');
        }

        $productUrl = $program->homepage_url ?: $program->tracking_url;

        if (! $productUrl) {
            throw new InvalidArgumentException('This program has no website address to research. Add the product by hand with its page address.');
        }

        $network = (string) (NetworkRegistry::config($program->connection->network)['affiliate_network_name'] ?? $program->connection->label());
        $offer = $this->research->queue($user, Str::limit($program->name, 255, ''), $productUrl, $network);

        try {
            $this->attach($offer, $program);
        } catch (NetworkException $e) {
            // The product still exists (research is running); the link can
            // be made again from the product page.
            report($e);
        }

        return $offer;
    }

    /**
     * Makes the program's tracked link the product's link.
     *
     * @throws NetworkException|InvalidArgumentException
     */
    public function attach(Offer $offer, NetworkProgram $program): Offer
    {
        $connection = $program->connection;

        if (! $connection || $connection->user_id !== $offer->user_id) {
            throw new InvalidArgumentException('That program belongs to a different account.');
        }

        $adapter = NetworkRegistry::adapter($connection->network);
        $destination = $program->deep_linking && $this->differsFromHomepage($offer->product_url, $program->homepage_url)
            ? $offer->product_url
            : null;

        $link = $adapter->trackingLink($connection, $program, $destination);

        if (! $link) {
            throw new NetworkException($connection->label().' didn\'t give a tracked link for this program. You can keep using your own affiliate link.');
        }

        $offer->update([
            'network_program_id' => $program->id,
            'network_link' => $link,
            'use_network_link' => true,
        ]);

        return $offer;
    }

    /**
     * Joined programs most likely to be this product (same website first).
     *
     * @return Collection<int, NetworkProgram>
     */
    public function candidatesFor(Offer $offer): Collection
    {
        $host = $this->baseHost((string) $offer->product_url);

        return NetworkProgram::query()
            ->with('connection')
            ->where('status', 'joined')
            ->whereHas('connection', fn ($query) => $query->where('user_id', $offer->user_id)->active())
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->sortByDesc(fn (NetworkProgram $program) => $host !== '' && $this->baseHost((string) $program->homepage_url) === $host)
            ->values();
    }

    public function matchesWebsite(Offer $offer, NetworkProgram $program): bool
    {
        $host = $this->baseHost((string) $offer->product_url);

        return $host !== '' && $host === $this->baseHost((string) $program->homepage_url);
    }

    protected function differsFromHomepage(?string $productUrl, ?string $homepage): bool
    {
        return filled($productUrl) && rtrim((string) $productUrl, '/') !== rtrim((string) $homepage, '/');
    }

    protected function baseHost(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return (string) preg_replace('/^www\./', '', $host);
    }
}
