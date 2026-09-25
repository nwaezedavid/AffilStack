<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A bearer token for AffilStack's API surface. Originally built for the
 * browser capture extension (Phase 3 backlog, item 11) and now the same
 * foundation for the general-purpose API (routes/api.php: /v1/*) and the
 * dashboard's "API Access" page — one token per purpose is unnecessary
 * since every /v1/* endpoint already scopes its response to
 * $request->user()'s own data (visibleOffers(), crmContacts(), etc.),
 * exactly like the dashboard itself.
 *
 * $type disambiguates an ordinary account token ('user', the default —
 * works for a user's own data, including a team seat's own scoped view)
 * from an 'admin' token, which only a full admin can mint (Filament: API
 * Tokens) and which alone unlocks /v1/admin/* — see EnsureAdminApiToken.
 *
 * API roadmap item #2: $scope narrows what a 'user' token may do —
 * 'full' (default) can call every verb; 'read_only' is rejected by
 * EnsureTokenScope on anything but GET/HEAD, so a token handed to a
 * contractor or a read-only reporting tool can never spend the wallet or
 * write data. Item #5: $is_sandbox flags a token whose calls never touch
 * real wallet/credit balances or real AI spend and only ever see/create
 * rows also flagged is_sandbox — see MeterApiUsage, OffersController, and
 * CrmContactsController.
 *
 * Deliberately not Laravel Sanctum — CLAUDE.md asks not to add
 * dependencies without approval — so this hand-rolls the same
 * well-established scheme Sanctum's own personal-access tokens use: a
 * random plaintext token shown to the user exactly once at creation, with
 * only its SHA-256 hash ever persisted. See App\Http\Middleware\ApiTokenAuth
 * for the other half.
 */
#[Fillable(['user_id', 'type', 'scope', 'is_sandbox', 'name', 'token_hash', 'last_used_at'])]
#[Hidden(['token_hash'])]
class ApiToken extends Model
{
    protected function casts(): array
    {
        return [
            'is_sandbox' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class, 'api_token_id');
    }

    /**
     * Creates a new token for $user and returns the ONE-TIME plaintext
     * alongside the persisted model — the caller must surface the plaintext
     * to the user immediately, since it can never be retrieved again.
     * $type: 'admin' tokens are minted only from the Filament API Tokens
     * resource (guarded there to full admins) — every other caller uses
     * the default. $scope/$isSandbox: dashboard-facing options on a normal
     * 'user' token only — the admin minting flow never passes either.
     *
     * @return array{token: ApiToken, plainText: string}
     */
    public static function generate(User $user, string $name, string $type = 'user', string $scope = 'full', bool $isSandbox = false): array
    {
        $plainText = 'aff_'.Str::random(40);

        $token = static::create([
            'user_id' => $user->id,
            'type' => $type,
            'scope' => $scope,
            'is_sandbox' => $isSandbox,
            'name' => $name,
            'token_hash' => hash('sha256', $plainText),
        ]);

        return ['token' => $token, 'plainText' => $plainText];
    }

    public static function findByPlainText(string $plainText): ?self
    {
        return static::where('token_hash', hash('sha256', $plainText))->first();
    }

    public function isAdminToken(): bool
    {
        return $this->type === 'admin';
    }

    public function isReadOnly(): bool
    {
        return $this->scope === 'read_only';
    }
}
