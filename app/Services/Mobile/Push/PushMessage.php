<?php

namespace App\Services\Mobile\Push;

use Illuminate\Support\Str;

/**
 * One phone notification: what it says and the AffilStack web address it
 * opens. The app turns that address into its own screen (or opens it in
 * the browser when it has no screen for it yet), the same way it handles
 * a tapped app link.
 */
final readonly class PushMessage
{
    public function __construct(
        public string $category,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    /**
     * @return array{category: string, title: string, body: string, url: string|null}
     */
    public function toArray(): array
    {
        return ['category' => $this->category, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    /**
     * @param  array{category: string, title: string, body: string, url?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['category'], $data['title'], $data['body'], $data['url'] ?? null);
    }

    /**
     * The message Expo sends on to Apple or Google for one phone. Lock
     * screens show little, so both texts are kept short.
     *
     * @return array<string, mixed>
     */
    public function forExpo(string $pushToken): array
    {
        return [
            'to' => $pushToken,
            'title' => Str::limit($this->title, 120),
            'body' => Str::limit($this->body, 240),
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => 'default',
            'data' => ['url' => $this->url, 'category' => $this->category],
        ];
    }
}
