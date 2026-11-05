<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row admin configuration for the paid lead data providers (Apollo,
 * Hunter) — see Filament: Integrations > Lead sources. Google Maps keeps its
 * own key (services.google_places.api_key).
 */
#[Fillable([
    'apollo_enabled', 'hunter_enabled', 'credentials',
    'apollo_test_status', 'apollo_test_message', 'hunter_test_status', 'hunter_test_message',
])]
class LeadSourceSetting extends Model
{
    protected function casts(): array
    {
        return [
            'apollo_enabled' => 'boolean',
            'hunter_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1, 'apollo_enabled' => false, 'hunter_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function apolloKey(): ?string
    {
        return $this->credential('apollo_api_key') ?: null;
    }

    public function hunterKey(): ?string
    {
        return $this->credential('hunter_api_key') ?: null;
    }

    public function apolloReady(): bool
    {
        return $this->apollo_enabled && $this->apolloKey() !== null;
    }

    public function hunterReady(): bool
    {
        return $this->hunter_enabled && $this->hunterKey() !== null;
    }
}
