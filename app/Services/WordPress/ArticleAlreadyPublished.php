<?php

namespace App\Services\WordPress;

use RuntimeException;

/**
 * The plugin refused to touch the article because it is already
 * published, scheduled or trashed in WordPress.
 */
class ArticleAlreadyPublished extends RuntimeException
{
    public function __construct(
        public ?int $postId = null,
        public ?string $postStatus = null,
        public ?string $editUrl = null,
    ) {
        parent::__construct('This article is already published on your site, so AffilStack left it alone. Edit it in WordPress instead.');
    }
}
