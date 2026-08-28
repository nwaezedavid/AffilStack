<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Feature 11 (Phase 3 backlog, item 11): a bearer token the browser capture
 * extension authenticates with. Deliberately not Laravel Sanctum — CLAUDE.md
 * asks not to add dependencies without approval — so this hand-rolls the
 * same well-established scheme Sanctum's own personal-access tokens use: a
 * random plaintext token shown to the user exactly once at creation, with
 * only its SHA-256 hash ever persisted. See App\Http\Middleware\ApiTokenAuth
 * for the other half.
 */
#[Fillable(['user_id', 'name', 'token_hash', 'last_used_at'])]
#[Hidden(['token_hash'])]
class ApiToken extends Model
{
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Creates a new token for $user and returns the ONE-TIME plaintext
     * alongside the persisted model — the caller must surface the plaintext
     * to the user immediately, since it can never be retrieved again.
     *
     * @return array{token: ApiToken, plainText: string}
     */
    public static function generate(User $user, string $name): array
    {
        $plainText = 'aff_'.Str::random(40);

        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainText),
        ]);

        return ['token' => $token, 'plainText' => $plainText];
    }

    public static function findByPlainText(string $plainText): ?self
    {
        return static::where('token_hash', hash('sha256', $plainText))->first();
    }
}
