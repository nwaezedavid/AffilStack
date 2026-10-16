<?php

namespace App\Services\WordPress;

use App\Jobs\SendArticleToWordPress;
use App\Models\Generation;
use App\Models\User;
use App\Models\WordpressDelivery;
use App\Models\WordpressSite;
use InvalidArgumentException;

/**
 * Queues articles for WordPress: when a customer clicks "Send to
 * WordPress", and automatically for every finished blog article on sites
 * where they turned on "Send new articles automatically".
 */
class WordPressDispatcher
{
    public const MODULES = ['blog_article'];

    public function __construct(protected WordPressSiteService $sites) {}

    /**
     * @throws InvalidArgumentException
     */
    public function send(User $user, Generation $generation, WordpressSite $site, bool $automatic = false): WordpressDelivery
    {
        if (! in_array($generation->module, self::MODULES, true) || $generation->status !== 'completed') {
            throw new InvalidArgumentException('Only finished blog articles can be sent to WordPress.');
        }

        if ($site->user_id !== $this->sites->owner($user)->id) {
            throw new InvalidArgumentException('That WordPress site isn\'t connected to your account.');
        }

        if (! $site->isConnected()) {
            throw new InvalidArgumentException('That WordPress site isn\'t connected right now. Reconnect it from the plugin\'s settings page.');
        }

        if (! $this->sites->included($user)) {
            throw new InvalidArgumentException('Your plan doesn\'t include the WordPress plugin. Upgrade your plan to send articles to WordPress.');
        }

        $delivery = WordpressDelivery::updateOrCreate(
            ['wordpress_site_id' => $site->id, 'generation_id' => $generation->id],
            ['status' => 'queued', 'error' => null, 'requested_by_id' => $user->id, 'automatic' => $automatic],
        );

        SendArticleToWordPress::dispatch($delivery);

        return $delivery;
    }

    /**
     * Called once a blog article finishes.
     */
    public function autoSend(Generation $generation): int
    {
        if (! in_array($generation->module, self::MODULES, true) || $generation->status !== 'completed' || ! $generation->user) {
            return 0;
        }

        $user = $generation->user;

        if (! $this->sites->included($user)) {
            return 0;
        }

        $sent = 0;

        foreach ($this->sites->owner($user)->wordpressSites()->connected()->where('auto_send', true)->get() as $site) {
            if (! $generation->wordpressDeliveries()->where('wordpress_site_id', $site->id)->exists()) {
                $this->send($user, $generation, $site, true);
                $sent++;
            }
        }

        return $sent;
    }
}
