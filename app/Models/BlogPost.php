<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post written (or AI-drafted) in AffilStack's admin and sent to the
 * WordPress blog — see App\Services\Blog\WordPressPublisher.
 */
#[Fillable([
    'created_by', 'title', 'slug', 'excerpt', 'content', 'featured_image_path',
    'categories', 'tags', 'focus_keyword', 'seo_title', 'seo_description',
    'wp_post_id', 'wp_status', 'wp_url', 'wp_featured_media_id', 'wp_featured_image_path',
    'synced_at', 'last_error',
])]
class BlogPost extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'tags' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOnWordPress(): bool
    {
        return $this->wp_post_id !== null;
    }

    /**
     * "Draft on the blog", "Published on the blog" or "Not sent yet".
     */
    public function blogStatusLabel(): string
    {
        return match ($this->wp_status) {
            'publish' => 'Published on the blog',
            'future' => 'Scheduled on the blog',
            'draft', 'pending' => 'Draft on the blog',
            'private' => 'Private on the blog',
            default => 'Not sent yet',
        };
    }
}
