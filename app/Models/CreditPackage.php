<?php

namespace App\Models;

use Database\Factories\CreditPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A one-time credit top-up a customer can buy on top of their plan's
 * recurring monthly grant — "users should be able to buy more credit tokens
 * if their monthly allocation finishes". See CreditTopupController for the
 * checkout flow and PaymentProcessor::process() for the 'credit_topup'
 * branch that actually grants the credits once payment verifies.
 */
#[Fillable(['name', 'description', 'credits', 'price_cents', 'currency', 'is_featured', 'is_active', 'sort_order'])]
class CreditPackage extends Model
{
    /** @use HasFactory<CreditPackageFactory> */
    use HasFactory;

    /**
     * Cached indefinitely and busted on save/delete — identical pattern to
     * Plan::activePublicList(), which this checkout page mirrors closely.
     *
     * @return Collection<int, self>
     */
    public static function activePublicList(): Collection
    {
        $rows = Cache::rememberForever('credit_packages:active_public_list', function () {
            return static::where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (self $package) => $package->getAttributes())->all();
        });

        return static::hydrate($rows);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('credit_packages:active_public_list'));
        static::deleted(fn () => Cache::forget('credit_packages:active_public_list'));
    }

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function price(): float
    {
        return $this->price_cents / 100;
    }

    /**
     * What a buyer effectively pays per credit — shown alongside each
     * package so the discount curve across packages is self-evident, the
     * same "why the bigger one is the better deal" job
     * Plan::priceMonthly()/priceYearly() do for annual billing.
     */
    public function pricePerCredit(): float
    {
        return $this->credits > 0 ? $this->price_cents / 100 / $this->credits : 0.0;
    }
}
